<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CourseMapImageUnavailable;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\GoogleStaticCourseMap;
use App\Support\ParticipantCourseMap;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CourseMapImageController extends Controller
{
    public function __invoke(Request $request, Category $category, GoogleStaticCourseMap $maps): Response
    {
        $category->load('event.categories');
        $public = $category->event && in_array($category->event->effective_status, ['upcoming', 'ongoing', 'completed'], true);
        $ownsRegistration = $category->registrations()->where('user_id', $request->user()->id)
            ->where('status', '!=', 'rejected')->exists();

        abort_unless($public && ($category->status === 'open' || $ownsRegistration), 404);

        $map = ParticipantCourseMap::make($category->course_map);
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];

        try {
            if ($map === null) {
                throw new CourseMapImageUnavailable('no_route', 404);
            }
            if (! is_string($request->query('revision')) || ! hash_equals($maps->revision($map), $request->query('revision'))) {
                throw new CourseMapImageUnavailable('route_changed', 409);
            }
            // A HEAD probe must never trigger a billable Google request.
            if ($request->isMethod('HEAD')) {
                throw new CourseMapImageUnavailable('get_required', 405);
            }

            return response($maps->image($map), 200, [...$headers, 'Content-Type' => 'image/png']);
        } catch (CourseMapImageUnavailable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'reason' => $exception->reason,
                'fallback_image_url' => $category->checkpoint_map_image ? asset('storage/'.$category->checkpoint_map_image) : null,
            ], $exception->status, $headers);
        }
    }
}
