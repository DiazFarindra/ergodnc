<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HostReservationController extends Controller
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
            'user_id' => ['integer'],
            'from_date' => ['date', 'required_with:to_date', 'before:to_date'],
            'to_date' => ['date', 'required_with:from_date', 'after:from_date'],
        ])->validate();

        $reservations = Reservation::query()
            ->whereRelation('office', 'user_id', '=', auth()->id())
            ->when(
                request('office_id'),
                fn ($query) => $query->where('office_id', request('office_id'))
            )
            ->when(
                request('user_id'),
                fn ($query) => $query->where('user_id', request('user_id'))
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
}
