<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\SliderImages;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class SliderImagesController extends Controller
{
    public function createSingleSliderImages(Request $request): JsonResponse
    {
        try {
            $file_paths = $request->file_paths;

            if (!$request->hasFile('images')) {
                return response()->json(['error' => 'image is required'], 422);
            }
            if (!$request->index) {
                return response()->json(['error' => 'index url is required'], 422);
            }

            //check index is unique
            $checkIndex = SliderImages::where('index', $request->index)->first();
            if ($checkIndex) {
                return response()->json(['error' => 'index already exists'], 422);
            }

            $createdSliderImages = SliderImages::create([
                'image' => $file_paths[0],
                'linkUrl' => $request->linkUrl ?? null,
                'index' => $request->index ?? null
            ]);
            if (!$createdSliderImages) {
                return response()->json(['error' => 'An error occurred during creating SliderImages. Please try again later.'], 500);
            }

            $converted = arrayKeysToCamelCase($createdSliderImages->toArray());
            return response()->json($converted, 201);
        } catch (Exception $exception) {
            return response()->json(['error' => 'An error occurred during creating SliderImages. Please try again later.'], 500);
        }
    }

    public function getAllSliderImages(Request $request): JsonResponse
    {
        if ($request->query('query') === 'all') {
            try {
                $sliderImages = SliderImages::where('status', 'true')->orderBy('id', 'desc')->get();
                if (!$sliderImages) {
                    return response()->json(['error' => 'SliderImages not found'], 404);
                }
                foreach ($sliderImages as $sliderImage) {
                    $currentUrl = url('/');
                    $sliderImage->image = $sliderImage->image ? $currentUrl . '/slider-image/' . $sliderImage->image : null;
                }

                $converted = arrayKeysToCamelCase($sliderImages->toArray());
                return response()->json($converted, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting SliderImages. Please try again later.'], 500);
            }
        } else {
            return response()->json(['error' => 'Invalid query'], 422);
        }
    }

    public function publicGetAllSliderImages(Request $request): JsonResponse
    {
        if ($request->query('query') === 'all') {
            try {
                $sliderImages = SliderImages::where('status', 'true')->orderBy('index', 'desc')->get();
                if (!$sliderImages) {
                    return response()->json(['error' => 'SliderImages not found'], 404);
                }
                foreach ($sliderImages as $sliderImage) {
                    $currentUrl = url('/');
                    $sliderImage->image = $sliderImage->image ? $currentUrl . '/slider-image/' . $sliderImage->image : null;
                }

                $converted = arrayKeysToCamelCase($sliderImages->toArray());
                return response()->json($converted, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting SliderImages. Please try again later.'], 500);
            }
        } else {
            return response()->json(['error' => 'Invalid query'], 422);
        }
    }

    public function updateSingleSliderImages(Request $request, $id): JsonResponse
    {
        try {
            $sliderImages = SliderImages::find($id);
            if (!$sliderImages) {
                return response()->json(['error' => 'SliderImages not found'], 404);
            }

            $oldImagePath = 'uploads/' . $sliderImages->image;

            if (Storage::exists($oldImagePath)) {
                Storage::delete($oldImagePath);
            }

            $file_paths = $request->file_paths;

            $update = SliderImages::where('id', $id)->update([
                'image' => $file_paths[0],
                'linkUrl' => $request->linkUrl
            ]);

            if (!$update) {
                return response()->json(['error' => 'An error occurred during updating SliderImages. Please try again later.'], 500);
            }
            return response()->json(['message' => 'SliderImages updated successfully'], 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during updating a single SliderImages. Please try again later.'], 500);
        }
    }

    public function deleteSlider(Request $request, $id)
    {
        try {
            $sliderImage = SliderImages::find($id);
            if (!$sliderImage) {
                return response()->josn(['error' => 'Slider Image Not Found!'], 500);
            }
            $oldImagePath = 'uploads/' . $sliderImage->image;

            if (Storage::exists($oldImagePath)) {
                Storage::delete($oldImagePath);
            }
            $sliderImage->delete();
            return response()->json(['success' => 'Slider Image Deleted Successfully!'], 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during updating a single SliderImages. Please try again later.'], 500);
        }
    }
}
