<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Validators\OfficeValidator;
use App\Notifications\OfficePendingApproval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OfficeController extends Controller
{
    public function index(): JsonResource
    {
        $offices = Office::query()
            ->when(request('user_id') && auth()->user() && request('user_id') == auth()->id(),
                fn ($builder) => $builder,
                fn ($builder) => $builder->where('approval_status', Office::APPROVAL_APPROVED)->where('hidden', false),
            )
            ->when(request('user_id'), fn ($builder) => $builder->where('user_id', request('user_id')))
            ->when(request('visitor_id'),
                fn (Builder $builder)
                    => $builder->whereRelation('reservations', 'user_id', '=', request('visitor_id'))
            )
            ->when(
                request('lat') && request('lng'),
                fn ($builder) => $builder->nearestTo(request('lat'), request('lng')),
                fn ($builder) => $builder->orderBy('id', 'asc')
            )
            ->with(['images', 'tags', 'user'])
            ->withCount(['reservations' => fn ($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
            ->paginate(20);

        return OfficeResource::collection(
            $offices
        );
    }

    public function show(Office $office)
    {
        $office->loadCount(['reservations' => fn ($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
            ->load(['images', 'tags', 'user']);

        return OfficeResource::make($office);
    }

    public function store(): JsonResource
    {
        if (! \auth()->user()->tokenCan('office.create')) {
            abort(Response::HTTP_FORBIDDEN);
        };

        $attributes = (new OfficeValidator())->validate(
            $office = new Office(),
            request()->all()
        );

        $attributes['approval_status'] = Office::APPROVAL_PENDING;
        $attributes['user_id'] = auth()->id();

        $office = DB::transaction(function () use ($office, $attributes) {
            $office->fill(
                Arr::except($attributes, ['tags'])
            )->save();

            if (isset($attributes['tags'])) {
                $office->tags()->attach($attributes['tags']);
            }

            return $office;
        });

        Notification::send(User::where('is_admin', true)->get(), new OfficePendingApproval($office));

        return OfficeResource::make(
            $office->load(['images', 'tags', 'user'])
        );
    }

    public function update(Office $office): JsonResource
    {
        if (! \auth()->user()->tokenCan('office.update')) {
            abort(Response::HTTP_FORBIDDEN);
        };

        $this->authorize('update', $office);

        $attributes = (new OfficeValidator())->validate($office, request()->all());

        $office->fill(Arr::except($attributes, ['tags']));

        if ($review = $office->isDirty(['lat', 'lng', 'price_per_day'])) {
            $office->fill(['approval_status' => Office::APPROVAL_PENDING]);
        }

        DB::transaction(function () use ($office, $attributes) {
            $office->save();

            if (isset($attributes['tags'])) {
                $office->tags()->sync($attributes['tags']);
            }
        });

        if ($review) {
            Notification::send(User::where('is_admin', true)->get(), new OfficePendingApproval($office));
        }

        return OfficeResource::make(
            $office->load(['images', 'tags', 'user'])
        );
    }

    public function destroy(Office $office)
    {
        if (! \auth()->user()->tokenCan('office.delete')) {
            abort(Response::HTTP_FORBIDDEN);
        };

        $this->authorize('delete', $office);

        if ($office->reservations()->where('status', Reservation::STATUS_ACTIVE)->exists()) {
            throw ValidationException::withMessages(['office' => 'cannot delete this office!']);
        }

        $office->images()->each(function ($image) {
            Storage::delete($image->path);
            $image->delete();
        });

        $office->delete();
    }
}
