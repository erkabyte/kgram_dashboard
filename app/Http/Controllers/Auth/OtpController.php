<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Otp;
use App\Models\User;
use App\Mail\OtpMail;
use App\Services\SmsService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class OtpController extends Controller
{
    protected $sms;

    public function __construct()
    {
        //SmsService $sms
        // $this->sms = $sms;
    }

    // Send OTP (email or phone). Request: { contact: string, method: 'email'|'sms' }
    public function send(Request $request)
    {
        $data = $request->only('contact', 'method');

        $validator = Validator::make($data, [
            'contact' => 'required|string',
            'method' => 'required|in:email,sms',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input', 'messages' => $validator->errors()], 422);
        }

        $contact = trim($data['contact']);
        $method = $data['method'];

        // Optional: find user by email or phone
        $user = null;
        if ($method === 'email') {
            $user = User::where('email', $contact)->first();
        } else {
            $user = User::where('phone', $contact)->first();
        }

        // Rate-limiting: count OTPs sent to this contact within last X minutes
        $recentSentCount = Otp::where('contact', $contact)
            ->where('created_at', '>=', Carbon::now()->subMinutes(10))
            ->count();

        if ($recentSentCount >= 5) {
            return response()->json(['error' => 'Too many OTP requests. Try later.'], 429);
        }

        // Generate numeric 6-digit code
        $code = random_int(100000, 999999);

        $minutes = (int) env('OTP_EXPIRY_MINUTES', 5);
        $expiresAt = Carbon::now()->addMinutes($minutes);

        $otp = Otp::create([
            'user_id' => $user ? $user->id : null,
            'contact' => $contact,
            'code_hash' => Hash::make($code),
            'expires_at' => $expiresAt,
        ]);

        // Send via chosen method
        if ($method === 'email') {
            // send email
            Mail::to($contact)->send(new OtpMail($code, $minutes));
        } else {
            // Send SMS via Twilio
            $message = "Your login code is {$code}. Expires in {$minutes} minutes.";
            try {
                $this->sms->send($contact, $message);
            } catch (\Exception $e) {
                // cleanup created OTP if sending failed
                $otp->delete();
                return response()->json(['error' => 'Failed to send SMS', 'details' => $e->getMessage()], 500);
            }
        }

        return response()->json(['message' => 'OTP sent', 'expires_at' => $expiresAt->toDateTimeString()], 200);
    }

    // Verify OTP. Request: { contact, code }
    public function verify(Request $request)
    {
        $validator = Validator::make($request->only('contact', 'code'), [
            'contact' => 'required|string',
            'code' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input', 'messages' => $validator->errors()], 422);
        }

        $contact = trim($request->contact);
        $code = $request->code;

        // find latest non-used OTP for contact
        $otp = Otp::where('contact', $contact)
            ->where('used', false)
            ->orderByDesc('created_at')
            ->first();

        if (! $otp) {
            return response()->json(['error' => 'No OTP found or already used'], 404);
        }

        if ($otp->isExpired()) {
            return response()->json(['error' => 'OTP expired'], 410);
        }

        if ($otp->attempts >= 5) {
            return response()->json(['error' => 'Too many attempts'], 429);
        }

        // Verify via Hash::check
        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            return response()->json(['error' => 'Invalid code'], 401);
        }

        // Mark used
        $otp->used = true;
        $otp->save();

        // Optionally, log user in or create user if none exists
        $user = $otp->user_id ? \App\Models\User::find($otp->user_id) : null;

        if (! $user) {
            // You may want to create a new user record for phone login - here's an example:
            // NOTE: Only create user automatically if desired for your app
            // $user = User::create([...]);
        }

        // Example: create session flag (if using session auth)
        session(['otp_authenticated_contact' => $contact, 'otp_authenticated_at' => now()]);

        // Or issue a token (JWT / Sanctum) — provide whichever fits your app
        return response()->json(['message' => 'OTP verified', 'user_id' => $user ? $user->id : null], 200);
    }
}
