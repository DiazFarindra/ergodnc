<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use Illuminate\Http\Request;

class UserReservationController extends Controller
{
    public function index()
    {
        if (! \auth()->user()->tokenCan('reservations.show')) {
            abort(Response::HTTP_FORBIDDEN);
        };

        // validation

        $reservations = Reservation::query()
            ->where('user_id', auth()->id())
            ->when(
                request('office_id'),
                fn ($query) => $query->where('office_id', request('office_id'))
            )
            ->when(
                request('status'),
                fn ($query) => $query->where('office_id', request('status'))
            )
            ->when(
                request('from_date') && request('to_date'),
                fn ($query) => $query->whereBetween('start_date', [request('from_date'), request('to_date')])
                                    ->orWhereBetween('end_date', [request('from_date'), request('to_date')])
            )
            ->with(['office.featuredImage'])
            ->paginate(20);

        return ReservationResource::collection($reservations);
    }
}
