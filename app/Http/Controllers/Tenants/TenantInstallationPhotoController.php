<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantInstallation;
use App\Models\Tenants\TenantInstallationPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TenantInstallationPhotoController extends Controller
{
    public function store(Request $request, TenantInstallation $installation)
    {
        $validated = $request->validate([
            'photo' => 'required|image|max:10240', // 10MB max
            'photo_type' => 'required|in:before,during,after,equipment,issue,completion',
            'caption' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $path = $request->file('photo')->store('installations/' . $installation->id, 'public');

        $photo = $installation->photos()->create([
            'photo_path' => $path,
            'photo_type' => $validated['photo_type'],
            'caption' => $validated['caption'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Photo uploaded successfully',
            'photo' => [
                'id' => $photo->id,
                'url' => $photo->photo_url,
                'type' => $photo->photo_type,
                'caption' => $photo->caption,
            ],
        ]);
    }

    public function bulkStore(Request $request, TenantInstallation $installation)
    {
        $validated = $request->validate([
            'photos' => 'required|array|max:20',
            'photos.*' => 'image|max:10240',
            'photo_type' => 'required|in:before,during,after,equipment,issue,completion',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $uploadedPhotos = [];

        foreach ($request->file('photos') as $photo) {
            $path = $photo->store('installations/' . $installation->id, 'public');

            $uploadedPhoto = $installation->photos()->create([
                'photo_path' => $path,
                'photo_type' => $validated['photo_type'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
            ]);

            $uploadedPhotos[] = [
                'id' => $uploadedPhoto->id,
                'url' => $uploadedPhoto->photo_url,
                'type' => $uploadedPhoto->photo_type,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => count($uploadedPhotos) . ' photos uploaded successfully',
            'photos' => $uploadedPhotos,
        ]);
    }

    public function update(Request $request, TenantInstallationPhoto $photo)
    {
        $validated = $request->validate([
            'caption' => 'nullable|string|max:500',
            'photo_type' => 'required|in:before,during,after,equipment,issue,completion',
        ]);

        $photo->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Photo updated successfully',
        ]);
    }

    public function destroy(TenantInstallationPhoto $photo)
    {
        $photo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Photo deleted successfully',
        ]);
    }

    public function getByInstallation(TenantInstallation $installation)
    {
        $photos = $installation->photos()
            ->with('uploader:id,name')
            ->latest()
            ->get()
            ->map(function ($photo) {
                return [
                    'id' => $photo->id,
                    'url' => $photo->photo_url,
                    'type' => $photo->photo_type,
                    'caption' => $photo->caption,
                    'latitude' => $photo->latitude,
                    'longitude' => $photo->longitude,
                    'taken_at' => $photo->taken_at,
                    'uploader' => $photo->uploader ? $photo->uploader->name : null,
                ];
            });

        return response()->json($photos);
    }

    public function getByType(TenantInstallation $installation, $type)
    {
        $photos = $installation->photos()
            ->byType($type)
            ->latest()
            ->get()
            ->map(function ($photo) {
                return [
                    'id' => $photo->id,
                    'url' => $photo->photo_url,
                    'caption' => $photo->caption,
                    'taken_at' => $photo->taken_at,
                ];
            });

        return response()->json($photos);
    }
}
