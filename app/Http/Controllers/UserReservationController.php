<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationResource;
use App\Models\Office;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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

        validator(request()->all(), [
            'office_id' => ['required', 'integer'],
            'start_date' => ['required', 'date:Y-m-d', 'require_with:end_date', 'after:' . now()->addDay()->toDateString()],
            'end_date' => ['required', 'date:Y-m-d', 'require_with:start_date', 'after:start_date'],
        ]);

        try {
            $office = Office::findOrFail(request('office_id'));
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


        // create reservation
        $reservation = Cache::lock('reservation_office_' . $office->id, 10)->block(3, function () use ($office) {
            // get total days
            $numberOfDays = Carbon::parse(request('end_date'))->endOfDay()->diffInDays(
                Carbon::parse(request('start_date'))->startOfDay()
            ) + 1;

            if ($numberOfDays < 2) {
                throw ValidationException::withMessages([
                    'start_date' => 'Cannot make reservation less then 2 days'
                ]);
            }

            if ($office->reservations()->activeBetween(request('start_date'), request('end_date'))->exists()) {
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
                'start_date' => request('start_date'),
                'end_date' => request('end_date'),
                'status' => Reservation::STATUS_ACTIVE,
                'price' => $price,
            ]);
        });

        return ReservationResource::make($reservation->load('office'));
    }
}
