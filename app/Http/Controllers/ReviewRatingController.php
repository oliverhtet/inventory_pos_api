<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Images;
use App\Models\ReviewReply;
use App\Models\ReviewRating;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class ReviewRatingController extends Controller
{
    public function createReviewRating(Request $request): JsonResponse
    {
        try {

            if ($request->query('query') == 'deletemany') {
                $data = json_decode($request->getContent(), true);
                $deleteReviewRating = ReviewRating::destroy($data);

                return response()->json(['count' => $deleteReviewRating], 200);
            } else {

                //check the customer already review or not
                $checkReview = ReviewRating::where('productId', $request->input('productId'))
                    ->where('customerId', $request->input('customerId'))
                    ->first();

                if ($checkReview) {
                    return response()->json([
                        'error' => 'You already review this product'
                    ], 404);
                }

                $createdProductReview = ReviewRating::create([
                    'productId' => $request->input('productId'),
                    'customerId' => $request->input('customerId'),
                    'rating' => (int)$request->input('rating'),
                    'review' => $request->input('review'),
                ]);

                if ($request->hasFile('images')) {
                    $file_paths = $request->file_paths;
                    if ($createdProductReview) {
                        foreach ($file_paths as $image) {
                            Images::create([
                                'reviewId' => $createdProductReview->id,
                                'imageName' => $image,
                            ]);
                        }
                    }
                }

                $converted = arrayKeysToCamelCase($createdProductReview->toArray());
                return response()->json($converted, 201);
            }
        } catch (Exception $error) {
            return response()->json([
                'error' => 'An error occurred during create review rating.Please try again later.'
            ]);
        }
    }

    public function getReviewRating(): JsonResponse
    {
        try {
            $pagination = getPagination(request()->query());
            $reviewRating = ReviewRating::with('reviewReply', "customer:id,username", 'product:id,name')
                ->where('status', request()->query('status'))
                ->orderBy('id', 'desc')
                ->skip($pagination['skip'])
                ->take($pagination['limit'])
                ->get();

            foreach ($reviewRating as $review) {
                $image = Images::where('reviewId', $review->id)->get();
                $review['images'] = $image->map(function ($item) {
                    return [
                        "id" => $item->id,
                        "ImageName" => $item->imageName
                    ];
                });
            }

            $converted = arrayKeysToCamelCase($reviewRating->toArray());

            $total = ReviewRating::where('status', request()->query('status'))
                ->count();
            $result = [
                'getAllReviewRating' => $converted,
                'totalReviewRating' => $total
            ];
            return response()->json($result, 200);
        } catch (Exception $error) {
            echo $error;
            return response()->json([
                'error' => 'An error occurred during get review rating.Please try again later.'
            ]);
        }
    }

    //get single review by customer id
    public function getSingleReviewByProductId(Request $request, $id): JsonResponse
    {
        try {
            $pagination = getPagination($request->query());
            $reviewRating = ReviewRating::with('customer:id,username', 'reviewReply', 'reviewReply.user:id,username')
                ->where('productId', $id)
                ->where('status', $request->query('status'))
                ->orderBy('id', 'desc')
                ->skip($pagination['skip'])
                ->take($pagination['limit'])
                ->get();

            foreach ($reviewRating as $review) {
                $image = Images::where('reviewId', $review->id)->get();
                $review['images'] = $image->map(function ($item) {
                    return [
                        "id" => $item->id,
                        "ImageName" => $item->imageName
                    ];
                });
            }

            $converted = arrayKeysToCamelCase($reviewRating->toArray());

            $total = ReviewRating::where('productId', $id)->where('status', $request->query('status'))->count();

            $result = [
                'getAllReviewRating' => $converted,
                'totalReviewRating' => $total
            ];

            return response()->json($result, 200);
        } catch (Exception $error) {
            return response()->json([
                'error' => 'An error occurred during get review rating.Please try again later.'
            ]);
        }
    }

    public function getSingleReviewRating($id): JsonResponse
    {
        try {
            $reviewRating = ReviewRating::with("reviewReply")->find($id);

            if (!$reviewRating) {
                return response()->json([
                    'error' => 'No review rating found'
                ], 404);
            }

            $image = Images::where('reviewId', $reviewRating->id)->get();
            $reviewRating['images'] = $image->map(function ($item) {
                return [
                    "id" => $item->id,
                    "ImageName" => $item->imageName
                ];
            });

            $converted = arrayKeysToCamelCase($reviewRating->toArray());
            return response()->json($converted, 200);
        } catch (Exception $error) {
            return response()->json([
                'error' => 'An error occurred during get single review rating.Please try again later.'
            ]);
        }
    }

    //review delete
    public function deleteReviewRating(Request $request, $id): JsonResponse
    {
        try {
            $reviewRating = ReviewRating::where('id', $id)->update(['status' => $request->input('status')]);

            if (!$reviewRating) {
                return response()->json([
                    'error' => 'No review rating found'
                ], 404);
            }

            return response()->json([
                'message' => 'Review rating deleted successfully'
            ], 200);
        } catch (Exception $error) {
            return response()->json([
                'error' => 'An error occurred during delete review rating.Please try again later.'
            ]);
        }
    }

    public function createReviewReply(Request $request): JsonResponse
    {
        try {
            $review = ReviewReply::find($request->input('reviewId'));
            if ($review !== null) {
                if ($request->input('comment') === null) {
                    return response()->json([
                        'message' => 'Already replied to this review'
                    ], 200);
                }
            }
            $data = $request->attributes->get('data');
            if ($data["role"] == 'admin') {

                $replyData = ReviewReply::where('reviewId', $request->input('reviewId'))
                    ->first();

                if ($replyData) {
                    if ($request->input('comment') !== null) {
                        $createdReviewReply = ReviewReply::where('reviewId', $request->input('reviewId'))
                            ->update([
                                'comment' => $request->input('comment'),
                            ]);
                    }
                } else {
                    $createdReviewReply = ReviewReply::create([
                        'reviewId' => $request->input('reviewId'),
                        'adminId' => $data["sub"],
                        'comment' => $request->input('comment'),
                    ]);
                }

                return response()->json(['message' => 'replied'], 201);
            } else {
                return response()->json([
                    'error' => 'You are not Unauthorized to create review reply'
                ], 404);
            }
        } catch (Exception $error) {
            echo $error;
            return response()->json([
                'error' => 'An error occurred during create review reply.Please try again later.'
            ]);
        }
    }
}
