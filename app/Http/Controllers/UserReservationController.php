<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationResource;
use App\Models\Office;
use App\Models\Reservation;
use App\Notifications\NewHostReservation;
use App\Notifications\NewUserReservation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserReservationController extends Controller
{
    public function index()
    {
        if (! \auth()->user()->tokenCan('reservations.show')) {
            abort(Response::HTTP_FORBIDDEN);
        };

        // validator
        validator(request()->all(), [
            'status' => [Rule::in([Reservation::STATUS_ACTIVE, Reservation::STATUS_CANCELED])],
            'office_id' => ['integer'],
            'from_date' => ['date', 'required_with:to_date', 'before:to_date'],
            'to_date' => ['date', 'required_with:from_date', 'after:from_date'],
        ])->validate();

        $reservations = Reservation::query()
            ->where('user_id', auth()->id())
            ->when(
                request('office_id'),
                fn ($query) => $query->where('office_id', request('office_id'))
            )
            ->when(
                request('status'),
                fn ($query) => $query->where('status', request('status'))
            )
            ->when(
                request('from_date') && request('to_date'),
                fn ($query) => $query->betweenDates(request('from_date'), request('to_date'))
            )
            ->with(['office.featuredImage'])
            ->paginate(20);

        return ReservationResource::collection($reservations);
    }

    public function store()
    {
        if (! \auth()->user()->tokenCan('reservations.store')) {
            abort(Response::HTTP_FORBIDDEN);
        };

        $data = validator(request()->all(), [
            'office_id' => ['required', 'integer'],
            'start_date' => ['required', 'date:Y-m-d', 'after:today'],
            'end_date' => ['required', 'date:Y-m-d', 'after:start_date'],
        ])->validate();

        try {
            $office = Office::findOrFail($data['office_id']);
        } catch (ModelNotFoundException $e) {
            throw ValidationException::withMessages([
                'office_id' => 'Invalid Office ID'
            ]);
        }

        if ($office->user_id == auth()->id()) {
            throw ValidationException::withMessages([
                'office_id' => 'You cannot make reservation on your own office'
            ]);
        }

        if ($office->hidden || $office->approval_status == Office::APPROVAL_PENDING) {
            throw ValidationException::withMessages([
                'office_id' => 'You cannot make a reservation on a hidden office'
            ]);
        }


        // create reservation
        $reservation = Cache::lock('reservation_office_' . $office->id, 10)->block(3, function () use ($office, $data) {
            // get total days
            $numberOfDays = Carbon::parse($data['end_date'])->endOfDay()->diffInDays(
                Carbon::parse($data['start_date'])->startOfDay()
            ) + 1;

            if ($office->reservations()->activeBetween($data['start_date'], $data['end_date'])->exists()) {
                throw ValidationException::withMessages([
                    'office_id' => 'Office is already reserved during this time'
                ]);
            }

            $price = $numberOfDays * $office->price_per_day;

            if ($numberOfDays >= 28 && $office->monthly_discount) {
                $price = $price - ($price * $office->monthly_discount / 100);
            }

            return Reservation::create([
                'user_id' => auth()->id(),
                'office_id' => $office->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => Reservation::STATUS_ACTIVE,
                'price' => $price,
                'wifi_password' => \Str::random()
            ]);
        });

        Notification::send(auth()->user(), new NewUserReservation($reservation));
        Notification::send($office->user, new NewHostReservation($reservation));

        return ReservationResource::make($reservation->load('office'));
    }

    public function cancel(Reservation $reservation)
    {
        if (! \auth()->user()->tokenCan('reservations.cancel')) {
            abort(Response::HTTP_FORBIDDEN);
        };

        if (
            $reservation->user_id != auth()->id() ||
            $reservation->status == Reservation::STATUS_CANCELED ||
            $reservation->start_date < now()->toDateString()
            ) {
            throw ValidationException::withMessages([
                'reservation' => 'you cannot cancel this reservation!'
            ]);
        }

        $reservation->update([
            'status' => Reservation::STATUS_CANCELED
        ]);

        return ReservationResource::make($reservation->load('office'));
    }
}
