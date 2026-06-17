<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\SalonReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'appointment_id' => ['required', 'integer'],
            'rating'         => ['required', 'integer', 'min:1', 'max:5'],
            'body'           => ['required', 'string', 'max:1000'],
        ]);

        $appointment = Appointment::findOrFail($data['appointment_id']);

        abort_if($appointment->user_id !== $request->user()->id, 403);
        abort_if($appointment->status !== 'completed', 403);

        $alreadyReviewed = SalonReview::where('appointment_id', $appointment->id)->exists();
        abort_if($alreadyReviewed, 409);

        SalonReview::create([
            'business_id'    => $appointment->business_id,
            'user_id'        => $request->user()->id,
            'appointment_id' => $appointment->id,
            'author_name'    => $request->user()->name,
            'rating'         => $data['rating'],
            'body'           => $data['body'],
            'is_published'   => false,
        ]);

        return back()->with('review_success', true);
    }
}
