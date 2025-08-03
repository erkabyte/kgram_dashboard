const togglePassword = document.querySelector('#togglePassword');
const password = document.querySelector('#password');

if (togglePassword) {
togglePassword.addEventListener('click', function () {
  const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
  password.setAttribute('type', type);
  this.classList.toggle('fa-eye-slash');
});
}

const toggleConfirmPassword = document.querySelector('#toggleConfirmPassword');
const confirm_password = document.querySelector('#confirm_password');
if (toggleConfirmPassword) {

  toggleConfirmPassword.addEventListener('click', function () {
    const type_confirm = confirm_password.getAttribute('type') === 'password' ? 'text' : 'password';
    confirm_password.setAttribute('type', type_confirm);
    this.classList.toggle('fa-eye-slash');
  });

}


const registerForm = document.querySelector('#registerForm');
const registerButton = document.querySelector('#register-button');
const errorMessage = document.querySelector('#error_message');

const baseUrl = document.querySelector('meta[name="base-url"]').getAttribute('content');



if (registerForm) {
  registerForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    const isValid = validateRegisterForm();
    if (!isValid) return;
    
    toggleRegisterButton(true, registerButton);
    errorMessage.textContent = '';

    try {
      const formData = new FormData(this);
      const response = await fetch(`${baseUrl}/api/register?is_ajax=1`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: formData
      });

      const data = await response.json();

      // Handle different response scenarios
      if (!response.ok) {
        if (data.errors) {
          const errorMessages = Object.values(data.errors).flat();
          errorMessage.textContent = errorMessages[0];
        } else if (data.message) {
          errorMessage.textContent = data.message;
        } else {
          errorMessage.textContent = 'An error occurred during registration';
        }
        return;
      }

      if (data.status === true) {
        // ... existing login code ...
        try {
          const formData = new FormData(this);
          const response = await fetch(`${baseUrl}/api/login?is_ajax=1`, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: formData
          });

          const data = await response.json();

          if (data.status == true) {
            window.location.href = `${baseUrl}`;
          }
        } catch (error) {
          if (error.message !== 'Validation Error') {
          }
        }

      } else {
        errorMessage.textContent = data.message || 'Registration failed';
      }
    } catch (error) {
      // Only show generic error for network/system errors
      console.error('Registration error:', error);
      errorMessage.textContent = 'A system error occurred. Please try again later.';
    } finally {
      toggleRegisterButton(false, registerButton);
    }
  });
}

function toggleRegisterButton(isSubmitting, button) {
  const registrationText = button.getAttribute('data-login-text') || 'Sign Up';
  button.textContent = isSubmitting ? 'Sign Up...' : registrationText;
  button.disabled = isSubmitting;
}

function validateRegisterForm() {
  let isValid = true;

  const firstName = registerForm.querySelector('input[name="first_name"]');
  const lastName = registerForm.querySelector('input[name="last_name"]');
  const email = registerForm.querySelector('input[name="email"]');
  const password = registerForm.querySelector('input[name="password"]');
  const confirmPassword = registerForm.querySelector('input[name="confirm_password"]');


  if (!firstName.value.trim()) {
    showValidationError(firstName, 'First Name field is required.');
    isValid = false;
  } else {
    clearValidationError(firstName);
  }

  if (!lastName.value.trim()) {
    showValidationError(lastName, 'Last Name field is required.');
    isValid = false;
  } else {
    clearValidationError(lastName);
  }

  if (email && email.required) {
    if (email.value.trim() === '') {
      showValidationError(email, 'Email field is required.');
      isValid = false;
    } else if (!validateEmail(email.value)) {
      showValidationError(email, 'Enter a valid Email Address.');
      isValid = false;
    } else {
      clearValidationError(email);
    }
  }

  if (!password.value.trim()) {

    showValidationError(password, 'Password field is required.');
    isValid = false;
  } else if (password.value.length < 6) {

    showValidationError(password, 'Password must be at least 6 characters long.');
    isValid = false;
  } else {
    clearValidationError(password);
  }

  if (password.value.length > 6 && password.value !== confirmPassword.value) {
    showValidationError(confirmPassword, 'Passwords and confirm password do not match.');
    isValid = false;
  } else {
    clearValidationError(confirmPassword);
  }

  return isValid;
}


function addInputListeners() {
  const formFields = [
    registerForm.querySelector('input[name="first_name"]'),
    registerForm.querySelector('input[name="last_name"]'),
    registerForm.querySelector('input[name="email"]'),
    registerForm.querySelector('input[name="password"]'),
    registerForm.querySelector('input[name="confirm_password"]')
  ];

  formFields.forEach(field => {
    if (field) {
      field.addEventListener('input', function() {
        clearValidationError(field);
        // Special handling for confirm password
        if (field.name === 'confirm_password' || field.name === 'password') {
          const password = registerForm.querySelector('input[name="password"]');
          const confirmPassword = registerForm.querySelector('input[name="confirm_password"]');
          if (password.value && confirmPassword.value) {
            if (password.value === confirmPassword.value) {
              clearValidationError(confirmPassword);
            }
          }
        }
      });
    }
  });
}

if (registerForm) {
  addInputListeners();
}

const loginForm = document.querySelector('#login-form');

if (loginForm) {
  const loginButton = document.querySelector('#login-button');
  const loginError = document.querySelector('#login_error_message');

  loginForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    const isValid = validateloginForm(); 
    if (!isValid) {
      return;
    }
    toggleLoginButton(true, loginButton);
    loginError.textContent = '';

    try {
      const formData = new FormData(this);
      const response = await fetch(`${baseUrl}/api/login?is_ajax=1`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: formData
      });

      const data = await response.json();

      if (!response.ok) {
        handleValidationErrors(data.errors);
      }
      
      if (data.status === true) {
        // Check for device limit
        if (data.device_limit_reached) {
          loginError.textContent = "Your device limit has been reached.";
          return;
        }
        window.location.href = `${baseUrl}`;
      } else {
        loginError.textContent = data.message;
      }
    } catch (error) {
      if (error.message !== 'Validation Error') {
        loginError.textContent = 'Your device limit has been reached.';
      }
    } finally {
      toggleLoginButton(false, loginButton);
    }
  });
}

  function validateloginForm() {
    let isValid = true;
    const emailField = loginForm.querySelector('input[name="email"]');
    const passwordField = loginForm.querySelector('input[name="password"]');

    if (emailField && emailField.required) {
      if (emailField.value.trim() === '') {
        showValidationError(emailField, 'Email field is required.');
        isValid = false;
      } else if (!validateEmail(emailField.value)) {
        showValidationError(emailField, 'Enter a valid Email Address.');
        isValid = false;
      } else {
        clearValidationError(emailField);
      }
    }

    if (passwordField && passwordField.value.trim() === '') {
      passwordField.classList.add('is-invalid');
      isValid = false;
    } else {
      passwordField.classList.remove('is-invalid');
    }

    return isValid;
  }


  function toggleLoginButton(isSubmitting, button) {
    const loginText = button.getAttribute('data-login-text') || 'Sign In';
    button.textContent = isSubmitting ? 'Sign In...' : loginText;
    button.disabled = isSubmitting;
  }



function validateEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}


function showValidationError(input, message) {
  const container = input.closest('.input-group');
  const errorFeedback = container.querySelector('.invalid-feedback');

  if (errorFeedback) {
    errorFeedback.textContent = message;
    input.classList.add('is-invalid');
  }
}

function clearValidationError(input) {

  const container = input.closest('.input-group');
  const errorFeedback = container.querySelector('.invalid-feedback');

  if (errorFeedback) {
    errorFeedback.textContent = '';
    input.classList.remove('is-invalid');
  }
}


const ForgetpasswordForm = document.querySelector('#forgetpassword-form');

if (ForgetpasswordForm) {

  const forgetpasswordButton = document.querySelector('#forget_password_btn');
  const ForgetpasswordError = document.querySelector('#forgetpassword_error_message');
  const Forgetpasswordmessage = document.querySelector('#forget_password_msg');

  
  ForgetpasswordForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    const isValid = validateforgetpasswordForm(); // Manually validate the form
    if (!isValid) {
      return;
    }
    toggleButton(true, forgetpasswordButton,'Sending...');
    ForgetpasswordError.textContent = '';

    try {
      const formData = new FormData(this);
      const response = await fetch(`${baseUrl}/api/forgot-password?is_ajax=1`, {
        method: 'post',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: formData
      });

      const data = await response.json();

      if (data.status == true) {
        Forgetpasswordmessage.classList.remove('d-none');
      } else {

        ForgetpasswordError.textContent = data.message
        toggleButton(false, forgetpasswordButton,'Submit');

      }
    } catch (error) {
      
      if (error.message !== 'Validation Error') {
      }
    } finally {
      toggleButton(false, forgetpasswordButton,'Submit');
    }
  });

}


function validateforgetpasswordForm() {
  let isValid = true;
  const emailField = ForgetpasswordForm.querySelector('input[name="email"]');
 
  if (emailField && emailField.required) {
    if (emailField.value.trim() === '') {
      showValidationError(emailField, 'Email field is required.');
      isValid = false;
    } else if (!validateEmail(emailField.value)) {
      showValidationError(emailField, 'Enter a valid Email Address.');
      isValid = false;
    } else {
      clearValidationError(emailField);
    }
  }


  return isValid;
}


function toggleButton(isSubmitting, button, btntext='Loding...') {
  const Text = button.getAttribute('data-login-text') || 'Submit';
  button.textContent = isSubmitting ? btntext : Text;
  button.disabled = isSubmitting;
}







