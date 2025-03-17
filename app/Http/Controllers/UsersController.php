<?php

namespace App\Http\Controllers;

use DateTime;
use Exception;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\{Users, Education, SalaryHistory, DesignationHistory};
use Firebase\JWT\JWT;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Hash, Cookie};

class UsersController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        try {
            $user = Users::where('username', $request->input('username'))->with('role:id,name')->first();

            if (!$user) {
                return response()->json(['error' => 'username or password is incorrect'], 401);
            }

            $pass = Hash::check($request->input('password'), $user->password);

            if (!$pass) {
                return response()->json(['error' => 'username or password is incorrect'], 401);
            }

            $token = [
                "sub" => $user->id,
                "roleId" => $user['role']['id'],
                "role" => $user['role']['name'],
                "exp" => time() + (60 * 60 * 6)
            ];

            $refreshToken = [
                "sub" => $user->id,
                "role" => $user['role']['name'],
                "exp" => time() + 86400 * 30
            ];

            $refreshJwt = JWT::encode($refreshToken, env('REFRESH_SECRET'), 'HS384');
            $jwt = JWT::encode($token, env('JWT_SECRET'), 'HS256');

            $cookie = Cookie::make('refreshToken', $refreshJwt, 60 * 24 * 30)->withPath('/')->withHttpOnly()->withSameSite('None')->withSecure();

            $userWithoutPassword = $user->toArray();

            $userWithoutPassword['role'] = $user['role']['name'];
            $userWithoutPassword['token'] = $jwt;

            $user->refreshToken = $refreshJwt;
            $user->isLogin = 'true';
            $user->save();
            unset($userWithoutPassword['password']);
            $converted = arrayKeysToCamelCase($userWithoutPassword);
            return response()->json($converted, 200)->withCookie($cookie);

        } catch (Exception $error) {
            return response()->json([
                'error' => $error->getMessage(),
            ], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $user = Users::findOrFail($request->id);
            $user->isLogin = 'false';
            $user->save();
            $cookie = Cookie::forget('refreshToken');

            return response()->json(['message' => 'Logout successfully'], 200)->withCookie($cookie);
        } catch (Exception $error) {
            return response()->json([
                'error' => $error->getMessage(),
            ], 500);
        }
    }

    public function register(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'username' => 'required|string|unique:users',
                'email' => 'required|email|unique:users',
                'password' => 'required|string',
            ]);

            $joinDate = new DateTime($request->input('joinDate'));
            $leaveDate = $request->input('leaveDate') ? new DateTime($request->input('leaveDate')) : null;

            $designationStartDate = Carbon::parse($request->input('designationStartDate'));
            $designationEndDate = $request->input('designationEndDate') ? Carbon::parse($request->input('designationEndDate')) : null;

            $salaryStartDate = Carbon::parse($request->input('salaryStartDate'));
            $salaryEndDate = $request->input('salaryEndDate') ? Carbon::parse($request->input('salaryEndDate')) : null;
            $hash = Hash::make($request->input('password'));

            $createUser = Users::create([
                'firstName' => $request->input('firstName'),
                'lastName' => $request->input('lastName'),
                'username' => $request->input('username'),
                'password' => $hash,
                'roleId' => $request->input('roleId'),
                'email' => $request->input('email'),
                'street' => $request->input('street'),
                'city' => $request->input('city'),
                'state' => $request->input('state'),
                'zipCode' => $request->input('zipCode'),
                'country' => $request->input('country'),
                'joinDate' => $joinDate->format('Y-m-d H:i:s'),
                'leaveDate' => $leaveDate?->format('Y-m-d H:i:s'),
                'employeeId' => $request->input('employeeId'),
                'phone' => $request->input('phone'),
                'bloodGroup' => $request->input('bloodGroup'),
                'image' => $request->input('image'),
                'designationId' => $request->input('designationId'),
                'employmentStatusId' => $request->input('employmentStatusId'),
                'departmentId' => $request->input('departmentId'),
                'shiftId' => $request->input('shiftId'),
            ]);

            DesignationHistory::create([
                'userId' => $createUser->id,
                'designationId' => $request->input('designationId'),
                'startDate' => $designationStartDate,
                'endDate' => $designationEndDate ?? null,
                'comment' => $request->input('designationComment') ?? null,
            ]);

            SalaryHistory::create([
                'userId' => $createUser->id,
                'salary' => $request->input('salary'),
                'startDate' => $salaryStartDate,
                'endDate' => $salaryEndDate ?? null,
                'comment' => $request->input('salaryComment') ?? null,
            ]);

            $educationData = collect($request->input('education'))->map(function ($education) use ($createUser) {
                $startDate = new DateTime($education['studyStartDate']);
                $endDate = isset ($education['studyEndDate']) ? new DateTime($education['studyEndDate']) : null;

                return [
                    'userId' => $createUser->id,
                    'degree' => $education['degree'],
                    'institution' => $education['institution'],
                    'fieldOfStudy' => $education['fieldOfStudy'],
                    'result' => $education['result'],
                    'studyStartDate' => $startDate->format('Y-m-d H:i:s'),
                    'studyEndDate' => optional($endDate)->format('Y-m-d H:i:s'),
                ];
            });

            Education::insert($educationData->toArray());

            unset($createUser['password']);
            $converted = arrayKeysToCamelCase($createUser->toArray());
            DB::commit();
            return response()->json($converted, 201);
        } catch (Exception $error) {
            DB::rollBack();
            return response()->json([
                'error' => $error->getMessage(),
            ], 500);
        }
    }

    // get all the user controller method
    public function getAllUser(Request $request): JsonResponse
    {
        if ($request->query('query') === 'all') {
            try {
                $allUser = Users::orderBy('id', "desc")
                    ->where('status', 'true')
                    ->with('saleInvoice')
                    ->get();

                $filteredUsers = $allUser->map(function ($u) {
                    return $u->makeHidden('password')->toArray();
                });

                $converted = arrayKeysToCamelCase($filteredUsers->toArray());

                //unset isLogin
                $converted = array_map(function ($user) {
                    unset ($user['isLogin']);
                    return $user;
                }, $converted);
                $finalResult = [
                    'getAllUser' => $converted,
                    'totalUser' => count($converted)
                ];

                return response()->json($finalResult, 200);
            } catch (Exception $error) {
                return response()->json([
                    'error' => $error->getMessage(),
                ], 500);
            }
        } elseif ($request->query('query') === 'search') {
            try {
                $pagination = getPagination($request->query());
                $key = trim($request->query('key'));

                $allUser = Users::where(function ($query) use ($key) {
                    return $query->orWhere('id', 'LIKE', '%' . $key . '%')
                        ->orWhere('username', 'LIKE', '%' . $key . '%')
                        ->orWere('firstName', 'LIKE', '%' . $key . '%')
                        ->orWhere('lastName', 'LIKE', '%' . $key . '%');
                })
                    ->where('status', 'true')
                    ->with('saleInvoice')
                    ->orderBy('id', "desc")
                    ->skip($pagination['skip'])
                    ->take($pagination['limit'])
                    ->get();

                $allUserCount = Users::where(function ($query) use ($key) {
                    return $query->where('id', 'LIKE', '%' . $key . '%')
                        ->orWhere('username', 'LIKE', '%' . $key . '%')
                        ->orWere('firstName', 'LIKE', '%' . $key . '%')
                        ->orWhere('lastName', 'LIKE', '%' . $key . '%');
                })
                    ->where('status', 'true')
                    ->count();

                $filteredUsers = $allUser->map(function ($u) {
                    return $u->makeHidden('password')->toArray();
                });

                $converted = arrayKeysToCamelCase($filteredUsers->toArray());

                //unset isLogin
                $converted = array_map(function ($user) {
                    unset ($user['isLogin']);
                    return $user;
                }, $converted);
                $finalResult = [
                    'getAllUser' => $converted,
                    'totalUser' => $allUserCount,
                ];

                return response()->json($finalResult, 200);
            } catch (Exception $error) {
                return response()->json([
                    'error' => $error->getMessage(),
                ], 500);
            }
        } elseif ($request->query()) {
            try {
                $pagination = getPagination($request->query());

                $allUser = Users::when('status', function ($query) use ($request) {
                    return $query->whereIn('status', explode(',', $request->query('status')));
                })
                    ->with('role:id,name')
                    ->orderBy('id', "desc")
                    ->skip($pagination['skip'])
                    ->take($pagination['limit'])
                    ->get()
                    ->toArray();

                $totalUser = Users::when('status', function ($query) use ($request) {
                    return $query->whereIn('status', explode(',', $request->query('status')));
                })
                    ->count();
                

                $converted = arrayKeysToCamelCase($allUser);

                $finalResult = [
                    'getAllUser' => $converted,
                    'totalUser' => $totalUser,
                ];

                return response()->json($finalResult, 200);
            } catch (Exception $error) {
                return response()->json([
                    'error' => $error->getMessage(),
                ], 500);
            }
        } else {
            return response()->json(['error' => 'Invalid query!'], 400);
        }
    }

    // get a single user controller method
    public function getSingleUser(Request $request): JsonResponse
    {
        try {
            $data = $request->attributes->get("data");
    
            if ($data['sub'] !== (int) $request['id'] && $data['role'] !== 'super-admin') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $singleUser = Users::where('id', $request['id'])
                ->with('saleInvoice', 'employmentStatus', 'shift', 'education', 'awardHistory.award', 'salaryHistory', 'designationHistory.designation', 'quote', 'role', 'department')
                ->first();

            if (!$singleUser) {
                return response()->json(['error' => 'User not found!'], 404);
            }

            $userWithoutPassword = $singleUser->toArray();
            unset($userWithoutPassword['password']);
            unset($userWithoutPassword['isLogin']);

            $converted = arrayKeysToCamelCase($userWithoutPassword);
            return response()->json($converted, 200);
        } catch (Exception $error) {
            return response()->json([
                'error' => $error->getMessage(),
            ], 500);
        }
    }

    public function updateSingleUser(Request $request, $id): JsonResponse
    {
        try {
           
            $joinDate = new DateTime($request->input('joinDate'));
            $leaveDate = $request->input('leaveDate') !== null ? new DateTime($request->input('leaveDate')) : null;
            
            if ($request->input('password')) {
                $hash = Hash::make($request->input('password'));
                $request->merge([
                    'password' => $hash,
                ]);
            }

            $joinDateString = $joinDate->format('Y-m-d H:i:s');
            $leaveDateString = $leaveDate?->format('Y-m-d H:i:s');

            $request->merge([
                'joinDate' => $joinDateString,
                'leaveDate' => $leaveDateString,
            ]);

            $user = Users::findOrFail((int) $id);

            if (!$user) {
                return response()->json(['error' => 'User not found!'], 404);
            }

            $user->update($request->all());
            $user->save();
            $userWithoutPassword = $user->toArray();
            unset($userWithoutPassword['password']);
            unset($userWithoutPassword['isLogin']);
            
            $converted = arrayKeysToCamelCase($userWithoutPassword);
            return response()->json($converted, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'User not found!'], 404);
        } catch (Exception $error) {
            echo $error;
            return response()->json([
                'error' => $error->getMessage(),
            ], 500);
        }
    }

    public function deleteUser(Request $request, $id): JsonResponse
    {
        try {
            //update the status
            $user = Users::findOrFail($id);

            if (!$user) {
                return response()->json(['error' => 'User not found!'], 404);
            }

            $user->status = $request->input('status');
            $user->save();

            return response()->json(['message' => 'User deleted successfully'], 200);
        } catch (Exception $error) {
            return response()->json([
                'error' => $error->getMessage(),
            ], 500);
        }
    }
}
