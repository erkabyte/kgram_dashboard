@extends('frontend::layouts.auth_layout')

@section('content')
    <div id="login">

        <div class="vh-100" style="background-image: url('{{ asset('/dummy-images/login_banner.jpg') }}')">
            <div class="container">
                <div class="row justify-content-center align-items-center height-self-center vh-100">

                    <div class="col-lg-5 col-md-8 col-11 align-self-center">
                        <div class="user-login-card card my-5">
                            <div class="text-center auth-heading">
                                @php
                                  $logo=GetSettingValue('dark_logo') ??  asset(setting('dark_logo'));
                                 @endphp

                                <img src="{{ $logo }}" class="img-fluid logo h-4 mb-4">

                                <h5>{{ __('frontend.sign_in_title') }}</h5>
                                <p class="fs-14">{{ __('frontend.sign_in_sub_title') }}</p>
                                @if (session()->has('error'))
                                    <span class="text-danger">{{ session()->get('error') }}</span>
                                @endif
                            </div>
                            <p class="text-danger" id="otp_error_message"></p>
                            <p class="text-success" id="otp_success_message"></p>
                            <p class="fs-14" id="otp_subtitle"></p>


                            <!-- Mobile Number Form -->
                            <div id="mobile-form">
                                <form id="send-otp-form" class="requires-validation" data-toggle="validator" novalidate>
                                    <div class="input-group mb-3">
                                        <span class="input-group-text px-0"><i class="ph ph-phone"></i></span>
                                        <input type="tel" id="mobile" value="1234567890" class="form-control"
                                            pattern="[0-9]{10}" placeholder="{{ __('frontend.enter_mobile') }}" required
                                            oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                                        <div class="invalid-feedback" id="mobile-error">Mobile number field is required.
                                        </div>
                                    </div>
                                    <div id="recaptcha-container" class="d-none"></div>
                                    <div class="full-button text-center">
                                        <button type="button" id="send-otp-button" class="btn btn-primary w-100"
                                            onclick="sendCode()">
                                            <span id="send-button-text">
                                                <i class="fa-solid fa-paper-plane"></i> {{ __('frontend.send_otp') }}
                                            </span>
                                            <span id="send-button-spinner" class="d-none">
                                                <span class="spinner-border spinner-border-sm" role="status"
                                                    aria-hidden="true"></span>
                                                Loading...
                                            </span>
                                        </button>
                                    </div>
                                </form>

                                <!-- <div class="border-style">
                                    <span>Or</span>
                                </div> -->

                                <!-- <div class="text-center">

                                    <a href="{{ route('auth.google') }}" class="d-block">
                                        <span id="google-login" class="btn btn-dark w-100">
                                            <svg class="me-1" width="16" height="16" viewBox="0 0 16 16"
                                                fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M3.4473 8.00005C3.4473 7.48042 3.5336 6.98224 3.68764 6.51496L0.991451 4.45605C0.465978 5.52296 0.169922 6.72515 0.169922 8.00005C0.169922 9.27387 0.465614 10.4753 0.990358 11.5415L3.68509 9.4786C3.53251 9.01351 3.4473 8.51715 3.4473 8.00005Z"
                                                    fill="#FBBC05" />
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M8.18202 3.27273C9.3109 3.27273 10.3305 3.67273 11.1317 4.32727L13.4622 2C12.042 0.763636 10.2213 0 8.18202 0C5.01608 0 2.29513 1.81055 0.992188 4.456L3.68838 6.51491C4.30962 4.62909 6.0805 3.27273 8.18202 3.27273Z"
                                                    fill="#EB4335" />
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M8.18202 12.7275C6.0805 12.7275 4.30962 11.3712 3.68838 9.48535L0.992188 11.5439C2.29513 14.1897 5.01608 16.0003 8.18202 16.0003C10.1361 16.0003 12.0016 15.3064 13.4018 14.0064L10.8425 12.0279C10.1204 12.4828 9.21112 12.7275 8.18202 12.7275Z"
                                                    fill="#34A853" />
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M15.8289 7.99996C15.8289 7.52723 15.756 7.01814 15.6468 6.54541H8.18164V9.63632H12.4786C12.2638 10.6901 11.679 11.5003 10.8421 12.0276L13.4014 14.0061C14.8722 12.641 15.8289 10.6076 15.8289 7.99996Z"
                                                    fill="#4285F4" />
                                            </svg>
                                            {{ __('frontend.continue_with_google') }}
                                        </span>
                                    </a>

                                    <a href="{{route('admin-login')}}" class="d-block mt-3"> {{__('installer_messages.final.admin_panel')}}</a>
                                </div> -->


                            </div>

                            <!-- OTP Verification Form -->
                            <div id="otp-form" style="display: none;">
                                <form id="verify-otp-form" class="requires-validation" data-toggle="validator" novalidate>
                                    <div class="input-group mb-3">
                                        <span class="input-group-text px-0"><i class="ph ph-lock-key"></i></span>
                                        <input type="text" name="otp" class="form-control"  value="123456"
                                            placeholder="{{ __('frontend.enter_otp') }}" aria-describedby="basic-addon1"
                                            id="otp" required>
                                        <div class="invalid-feedback" id="otp-error">OTP field is required.</div>
                                    </div>
                                    <div id="otp-timer" style="color: red; display: none;">You can resend the OTP in <span
                                            id="timer">
                                        </span> seconds.</div>
                                    <div class="full-button text-center">
                                        <button type="button" id="verify-otp-button" class="btn btn-primary w-100"
                                            onclick="verifyCode()">
                                            <span id="button-text">
                                                <i class="fa-solid fa-floppy-disk"></i> {{ __('frontend.verify_otp') }}
                                            </span>
                                            <span id="button-spinner" class="d-none">
                                                <span class="spinner-border spinner-border-sm" role="status"
                                                    aria-hidden="true"></span>
                                                Loading...
                                            </span>
                                        </button>
                                        <div id="resend_code">
                                            <p class="mt-2 mb-0 fw-normal">{{ __('frontend.not_receive_otp') }}
                                                <a type="button" href="#" class="ms-1" id="resend-otp"
                                                    onclick="resendCode()">{{ __('frontend.resend_otp') }}</a>
                                            </p>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <div id="registerForm" style="display: none;">
                                <form action="{{ route('auth.otp-login-store') }}" method="post"
                                    class="requires-validation" data-toggle="validator" novalidate>
                                    @csrf
                                    <div class="input-group mb-3">
                                        <span class="input-group-text px-0"><i class="ph ph-phone"></i></span>
                                        <input type="text" name="mobile" id="mobile_number" class="form-control"
                                            placeholder="{{ __('frontend.enter_mobile') }}"
                                            aria-describedby="basic-addon1" required readonly>
                                        <div class="invalid-feedback" id="mobile-error">Mobile number field is required.
                                        </div>
                                    </div>

                                    <div class="input-group mb-3">
                                        <span class="input-group-text px-0"><i class="ph ph-user"></i></span>
                                        <input type="text" name="first_name" class="form-control"
                                            placeholder="{{ __('frontend.enter_fname') }}" required>
                                        <div class="invalid-feedback" id="first_name_error">First Name field is required
                                        </div>
                                    </div>
                                    <div class="input-group mb-3">
                                        <span class="input-group-text px-0"><i class="ph ph-user"></i></span>
                                        <input type="text" name="last_name" class="form-control"
                                            placeholder="{{ __('frontend.enter_lname') }}" required>
                                        <div class="invalid-feedback" id="last_name_error">Last Name field is required
                                        </div>
                                    </div>
                                    <div class="input-group mb-3">
                                        <span class="input-group-text px-0"><i class="ph ph-envelope"></i></span>
                                        <input type="text" name="email" class="form-control"
                                            placeholder="{{ __('frontend.enter_email') }}" required>
                                        <div class="invalid-feedback" id="email_error">Email field is required</div>
                                    </div>

                                    <div class="full-button text-center">
                                        <button type="submit" id="register-button" class="btn btn-primary w-100"
                                            data-signup-text="{{ __('frontend.sign_up') }}">
                                            {{ __('frontend.sign_up') }}
                                        </button>
                                    </div>
                                </form>
                            </div>


                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://www.gstatic.com/firebasejs/6.0.2/firebase.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/css/intlTelInput.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/intlTelInput.min.js"></script>

    <script>
        var isOtpLoginEnabled = {{ json_encode($isOtpLoginEnabled) }};

        if (isOtpLoginEnabled) {
            var firebaseConfig = {
                @foreach ($settings as $setting)
                    @if (in_array($setting->name, [
                            'apiKey',
                            'authDomain',
                            'databaseURL',
                            'projectId',
                            'storageBucket',
                            'messagingSenderId',
                            'appId',
                            'measurementId',
                        ]))
                        '{{ $setting->name }}': '{{ $setting->val }}',
                    @endif
                @endforeach
            };


            firebase.initializeApp(firebaseConfig);
        } else {
            console.log('OTP login is disabled. Firebase not initialized.');
        }
    </script>

    <script type="text/javascript">
        window.onload = function() {
            render();
        }

        function render() {
            window.recaptchaVerifier = new firebase.auth.RecaptchaVerifier('recaptcha-container', {
                size: 'invisible'
            });
            recaptchaVerifier.render();
        }
        var input = document.querySelector("#mobile");
        var iti = window.intlTelInput(input, {
            initialCountry: "in", // Automatically detect user's country
            separateDialCode: true, // Show the country code separately
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js" // To handle number formatting
        });

        let timerInterval;
        var number = '';


        function sendCode() {
    var number = iti.getNumber();

    if (iti.isValidNumber()) {
        // Disable the button and show spinner while processing
        document.getElementById('send-otp-button').disabled = true;
        document.getElementById('send-button-text').classList.add('d-none');
        document.getElementById('send-button-spinner').classList.remove('d-none');

        // Initiate Firebase sign-in with phone number
        firebase.auth().signInWithPhoneNumber(number, window.recaptchaVerifier)
            .then(function(confirmationResult) {
                // Successfully sent OTP
                window.confirmationResult = confirmationResult;
                coderesult = confirmationResult;

                $('#mobile-form').hide();
                $('#otp_error_message').text("");
                $('#otp-form').show();

                $('#otp_title').text('Verify OTP');
                $('#otp_subtitle').text('We’ve sent an OTP to your mobile number. Please enter it to proceed.');

                startOtpTimer();
            })
            .catch(function(error) {
                // Initialize error message variable
                let errorMessage;

                // Handle invalid phone number error
                if (error.code === 'auth/invalid-phone-number') {
                    errorMessage = "Enter a valid mobile number";
                } else {
                    // Handle other errors, especially when error.message is a JSON string
                    try {
                        const errorData = JSON.parse(error.message);
                        if (errorData.error && errorData.error.errors && Array.isArray(errorData.error.errors)) {
                            const specificError = errorData.error.errors.find(err => err.message === "BILLING_NOT_ENABLED");
                            if (specificError) {
                                errorMessage = "Currently, you can use only demo login credentials. Please upgrade your Firebase billing details.";
                            }
                        }
                    } catch (e) {
                        // JSON parsing failed, fallback to raw error message
                        console.error("Error parsing error.message:", e);
                    }

                    // Fallback to the original error message if no specific error is found
                    if (!errorMessage) {
                        errorMessage = error.message || "An unexpected error occurred.";
                    }
                }

                // Display the error message
                $('#otp_error_message').text(errorMessage).show();
            })
            .finally(function() {
                // Re-enable the button and hide the spinner after the process completes
                document.getElementById('send-otp-button').disabled = false;
                document.getElementById('send-button-text').classList.remove('d-none');
                document.getElementById('send-button-spinner').classList.add('d-none');
            });
    } else {
        // Invalid phone number case
        $('#mobile-error').text('Invalid phone number');
        $('#mobile-error').show();
    }
}



        function startOtpTimer() {
            let timeLeft = 60;
            $('#otp-timer').show();
            $('#resend_code').addClass('d-none');
            $('#timer').text(60);

            timerInterval = setInterval(function() {
                timeLeft--;
                $('#timer').text(timeLeft);

                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    $('#otp-timer').hide();
                    $('#resend_code').removeClass('d-none');
                }
            }, 1000);
        }


        function verifyCode() {
            var code = $('#otp').val();

            if (code == '') {

                $('.invalid-feedback').css('display', 'block');
                $('#otp-error').text('OTP is required field');
                return;
            }

            // Show loading spinner and disable the button
            document.getElementById('verify-otp-button').disabled = true;
            document.getElementById('button-text').classList.add('d-none');
            document.getElementById('button-spinner').classList.remove('d-none');

            var numbervalue = iti.getNumber()

            coderesult.confirm(code).then(function(result) {
                var user = result.user;
                $.ajax({
                    url: '{{ route('check.user.exists') }}', // Replace with your API URL
                    type: 'get', // Use POST method
                    data: {
                        user_id: user.uid, // Example of sending the user data
                        mobile: numbervalue, // Send the mobile number as well
                    },
                    success: function(response) {

                        if (response.is_user_exists == 0) {
                            $('#otp-form').hide();
                            $('#otp_title').text('Personal Details');
                            $('#otp_subtitle').text(
                                'Please provide additional details to complete signup');
                            $('#mobile_number').val(numbervalue);
                            $('#otp_error_message').text('');
                            $('#registerForm').show();
                        }

                        if (response.status == 406) {

                            $('#mobile-form').show();
                            $('#otp-form').hide();
                            $('#otp_error_message').text(response.message);
                            $('#otp_error_message').show();

                        }

                        if (response.url && response.is_user_exists == 1) {
                            window.location = response.url;
                        }
                    },
                    error: function(error) {
                        $('#otp_error_message').text(error);
                        $('#otp_error_message').show();
                    },
                    complete: function() {
                        // Re-enable the button and hide the spinner after the request is complete
                        document.getElementById('verify-otp-button').disabled = false;
                        document.getElementById('button-text').classList.remove('d-none');
                        document.getElementById('button-spinner').classList.add('d-none');
                    }
                });

            }).catch(function(error) {
                $('#otp_error_message').text(error.message);
                $('#otp_error_message').show();
                document.getElementById('verify-otp-button').disabled = false;
                document.getElementById('button-text').classList.remove('d-none');
                document.getElementById('button-spinner').classList.add('d-none');
            });

        }

        setTimeout(function() {
            $('#otp_error_message').hide(); // Hide the error message after 2 seconds (2000 milliseconds)
            $('#otp_success_message').hide();
        }, 2000);



        function resendCode() {
            var number = iti.getNumber();

            if (!iti.isValidNumber()) {
                $('#otp_error_message').text("Invalid phone number format.").show();
                return;
            }

            $('#otp_error_message').text("").hide();

            if (!window.recaptchaVerifier) {
                window.recaptchaVerifier = new firebase.auth.RecaptchaVerifier('recaptcha-container', {
                    size: 'invisible'
                });
            }

            window.recaptchaVerifier.render().then(function() {
                firebase.auth().signInWithPhoneNumber(number, window.recaptchaVerifier)
                    .then(function(confirmationResult) {
                        window.confirmationResult = confirmationResult;

                        startOtpTimer();
                    })
                    .catch(function(error) {
                        $('#otp_error_message').text(error.message).show();
                    });
            });
        }
    </script>
@endsection
