<?php

namespace App\Http\Controllers;

use App\Http\Resources\ImageResource;
use App\Models\Image;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OfficeImageController extends Controller
{
    public function store(Office $office): JsonResource
    {
        if (! \auth()->user()->tokenCan('office.update')) {
            abort(Response::HTTP_FORBIDDEN);
        };

        $this->authorize('update', $office);

        request()->validate([
            'image' => ['file', 'max:5000', 'mimes:png,jpg']
        ]);

        $path = request()->file('image')->storePublicly('/');

        $image = $office->images()->create([
            'path' => $path
        ]);

        return ImageResource::make($image);
    }

    public function destroy(Office $office, Image $image)
    {
        if (! \auth()->user()->tokenCan('office.update')) {
            abort(Response::HTTP_FORBIDDEN);
        };

        $this->authorize('update', $office);

        if ($office->images()->count() == 1) {
            throw ValidationException::withMessages(['image' => 'cannot delete the only image']);
        }

        if ($office->featured_image_id == $image->id) {
            throw ValidationException::withMessages(['image' => 'cannot delete the featured image']);
        }

        Storage::delete($image->path);
        $image->delete();
    }
}
