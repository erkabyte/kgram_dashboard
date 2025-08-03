<div class="section-spacing-bottom">
    <div class="container">
        <a href="{{route('subscriptionPlan')}}" class="text-decoration-none text-white flex-none"><i class="ph ph-caret-left"></i><span class="font-size-18 fw-medium">{{__('frontend.back_to_subscription_plan')}}</span></a>
        <div class="mt-5">
            <div class="row">
                <div class="col-lg-3">

                    <form id="plan-form">
                        @foreach ($plans as $plan)
                            <div class="col-12 mb-4">
                                <label class="form-check stripe-payment-form p-4 position-relative rounded" for="{{ strtolower($plan->name) }}">
                                    @if($plan->discount>0)

                                    <input type="radio" id="{{ strtolower($plan->id) }}" name="plan_name" value="{{ $plan->id }}" data-amount="{{ $plan->total_price }}" class="form-check-input payment-radio-btn">
                                    @else

                                    <input type="radio" id="{{ strtolower($plan->id) }}" name="plan_name" value="{{ $plan->id }}" data-amount="{{ $plan->price }}" class="form-check-input payment-radio-btn">
                                    @endif
                                    <span class="form-check-label">
                                        <span class="text-uppercase fw-medium d-block mb-2">{{ $plan->name }}</span>
                                        @if($plan->discount>0)
                                        <span class="h4">   {{ Currency::format($plan->total_price) }}  <del> {{  Currency::format($plan->price) }}</del><span class="font-size-14 text-body">/ {{ $plan->duration_value }} {{ $plan->duration }} </span></span>
                                        @else
                                        <span class="h4"> {{ Currency::format($plan->price) }} <span class="font-size-14 text-body">/ {{ $plan->duration_value }}  {{ $plan->duration }}</span></span>
                                        @endif
                                    </span>
                                </label>
                            </div>
                        @endforeach
                    </form>
                </div>
                <div class="col-lg-9 mt-lg-0 mt-5">
                    <form action="{{ route('process-payment') }}" method="POST" id="payment-form">
                        @csrf
                        <div class="form-group">
                            <input type="hidden" id="selected-plan-id" name="plan_id">
                            <input type="hidden" id="selected-price" name="price">
                            <label class="form-label" for="payment-method">{{ __('frontend.choose_payment_method') }}:</label>
                            <select id="payment-method" name="payment_method" class="form-select">
                                <option value="" selected disabled>{{ __('frontend.select_payment_method') }}</option>
                                @php
                                    $payment_methods = [
                                        'str_payment_method' => 'stripe',
                                        'razor_payment_method' => 'razorpay',
                                        'paystack_payment_method' => 'paystack',
                                        'flutterwave_payment_method' => 'flutterwave',
                                        'cinet_payment_method' => 'cinet',
                                        'sadad_payment_method' => 'sadad',
                                        'airtel_payment_method' => 'airtel',
                                        'phonepe_payment_method' => 'phonepe',
                                        'midtrans_payment_method' => 'midtrans'
                                    ];
                                @endphp
                                @foreach ($payment_methods as $setting => $method)
                                    @if (setting($setting) == 1)
                                        <option value="{{ $method }}">{{ __('frontend.' . $method) }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>


                    <div class="mt-4">
                        <div class="payment-detail rounded">
                            <h6 class="font-size-18">{{__('frontend.payment_details')}}</h6>
                            <div class="table-responsive">
                                <table class="table table-borderless">
                                    <tbody>

                                        <tr>
                                            <td>{{__('frontend.price')}}</td>
                                            <td><h6 class="font-size-18 text-end mb-0" id="price"></h6></td>
                                        </tr>

                                        <tr id="discount_class" class="d-none">
                                            <td class="d-flex gap-2">Discount  <h6 id="discount_data"></h6></td>
                                            <td><h6 class="font-size-18 text-end mb-0" id="discount"></h6></td>
                                        </tr>

                                        <tr id="subtotal_class" class="d-none">
                                            <td>{{__('frontend.subtotal')}}</td>
                                            <td><h6 class="font-size-18 text-end mb-0" id="subtotal"></h6></td>
                                        </tr>
                                        <tr id="tax_tr">
                                            <td><p>{{__('frontend.tax')}}</p>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center justify-content-end gap-2">
                                                    <span><i class="ph ph-info" aria-hidden="true" data-bs-toggle="modal" data-bs-target="#appliedTax"></i></span>

                                                    <h6 class="font-size-18 text-end text-primary mb-0" id="tax"></h6>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="border-bottom">
                                            <td>{{__('frontend.total')}}</td>
                                            <td><h6 class="font-size-18 text-end mb-0" id="total"></h6></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class="mt-4">
                                    <div class="d-flex justify-content-between gap-3">
                                        <h6>{{__('frontend.total_payment')}}</h6>
                                        <div class="d-flex justify-content-center align-items-center gap-3">
                                            <h5 class="mb-0" id="total-payment"></h5>
                                            <small><del id="old-price"></del></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="d-flex justify-content-end">
                                <div class="d-flex justify-content-center align-items-center gap-4 flex-wrap">
                                    <div class="d-flex justify-content-center align-items-center gap-2">
                                        <i class="ph ph-lock-key text-primary"></i>
                                        <p class="mb-0">{{__('frontend.payment_secure')}}</p>
                                    </div>
                                    <button type="submit" class="btn btn-primary">{{__('frontend.proceed_payment')}}</button>


                                   <div class="modal fade" id="appliedTax" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header border-0">
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body text-center">
                                                    <div class="ph-circle-check text-primary font-size-140"></div>
                                                    <h5 class="font-size-28 mb-4">Applied Taxes</h5>

                                                    <div id="applied_tax">


                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-center d-none">
                                        <div class="ph-circle-check text-primary font-size-140"></div>
                                        <h5 class="font-size-28 mb-4">{{__('frontend.thanks_for_payment')}}</h5>
                                        <p>{{__('frontend.payment_success')}}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">

                        </div>
                    </div>
                </div>
            </div>
        </div>
        </form>
        <div class="modal fade" id="errorModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content text-center">
                    <div class="modal-header justify-content-center">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p id="errorModalMessage"></p>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script src="https://checkout.flutterwave.com/v3.js"></script>
        <script>


    function formatCurrencyvalue(value){
           if (window.currencyFormat !== undefined) {
             return window.currencyFormat(value)
           }
           return value
        }

            $(document).ready(function() {
             @if(session('error'))
             $('#errorModalMessage').text('{{ session('error') }}');
             var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
             errorModal.show();
             @endif
             const baseUrl = document.querySelector('meta[name="baseUrl"]').getAttribute('content');
             var selectedPlanId = @json($planId); // Injected from the backend
             if (selectedPlanId) {
                 $('input[type="radio"][value="' + selectedPlanId + '"]').prop('checked', true);
                 $('#selected-plan-id').val(selectedPlanId);
                 $('#selected-price').val($('input[type="radio"][value="' + selectedPlanId + '"]').data('amount'));
                 updatePaymentDetails(selectedPlanId);
             }
             $('.payment-radio-btn').on('change', function() {
                 var selectedPrice = $(this).data('amount');
                 var selectedPlanId = $(this).val();
                 $('#selected-price').val(selectedPrice);
                 $('#selected-plan-id').val(selectedPlanId);
                 updatePaymentDetails(selectedPlanId);
             });


             function updatePaymentDetails(planId) {

                $('#discount_class').addClass('d-none');
                $('#subtotal_class').addClass('d-none');

                 $.ajax({
                     url: `${baseUrl}/get-payment-details`,
                     method: 'POST',
                     data: {
                         plan_id: planId,
                         _token: '{{ csrf_token() }}'
                     },
                     success: function(response) {
                         $('#selected-price').val(response.subtotal + response.tax);
                         $('#price').text(formatCurrencyvalue(response.price));
                         $('#tax').text('+' + formatCurrencyvalue(response.tax));
                         $('#total').text(formatCurrencyvalue(response.total));
                         $('#total-payment').text(formatCurrencyvalue(response.total));

                         if(response.discount_amount>0){

                            $('#discount_class').removeClass('d-none');
                            $('#subtotal_class').removeClass('d-none');

                            $('#discount_data').text('('+ response.discount +'%)');

                            $('#discount').text('-' + formatCurrencyvalue(response.discount_amount));
                            $('#subtotal').text( formatCurrencyvalue(response.subtotal));

                         }
                         createTaxTable(response.tax_array);

                         if(response.tax === 0) {
                             $('#tax_tr').addClass('d-none');
                         }else{
                             $('#tax_tr').removeClass('d-none');
                         }
                     },
                     error: function(xhr) {
                         $('#errorModalMessage').text('An error occurred while fetching payment details.');
                         var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                         errorModal.show();
                     }
                 });
             }

             function createTaxTable(taxes) {
                 let tableHtml = '<table class="table"><tbody>';
                 taxes.forEach(function(tax) {
                     tableHtml += '<tr>';
                     if (tax.type.toLowerCase() === 'percentage') {
                         tableHtml += `<td>${tax.name} (${tax.value}%)</td>`;
                     } else {
                         tableHtml += `<td>${tax.name} ($${tax.value})</td>`;
                     }
                     tableHtml += `<td>$${tax.tax_amount}</td>`;
                     tableHtml += '</tr>';
                 });
                 tableHtml += '</tbody></table>';
                 $('#applied_tax').html(tableHtml);
             }

             $('#payment-form').on('submit', function(e) {
                 e.preventDefault(); // Prevent default form submission
                 const paymentMethod = $('#payment-method').val();
                if (!paymentMethod) {
                    $('#errorModalMessage').text('Please select a payment method before proceeding.');
                    var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                    errorModal.show();
                    return; // Exit the function
                }
                 const formData = $(this).serialize();
                 $.ajax({
                     url: $(this).attr('action'),
                     method: 'POST',
                     data: formData,
                     success: function(response) {
                         if (response.redirect) {
                             window.location.href = response.redirect;
                         }
                     },
                     error: function(xhr) {
                         const errorResponse = xhr.responseJSON || {};
                         const errorMessage = errorResponse.error || 'An error occurred. Please try another payment method.';
                         // Display an error modal using Bootstrap
                         $('#errorModalMessage').text(errorMessage);
                         var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                         errorModal.show();
                     }
                 });
             });

// Razor Pay
             $('#payment-form').on('submit', function(e) {

                if (document.getElementById('payment-method').value !== 'razorpay') {
                    return true; 
                }

                e.preventDefault();

                $.ajax({
                    url: $(this).attr('action'),
                    type: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        var options = {
                            "key": response.key,
                            "amount": response.amount,
                            "currency": response.currency,
                            "name": response.name,
                            "description": response.description,
                            "order_id": response.order_id,
                            "handler": function (paymentResponse){
                                const successUrl = new URL(response.success_url);
                                successUrl.searchParams.append('gateway', 'razorpay');
                                successUrl.searchParams.append('razorpay_payment_id', paymentResponse.razorpay_payment_id);
                                successUrl.searchParams.append('plan_id', response.plan_id);
                                
                                window.location.href = successUrl.toString();
                            },
                            "prefill": {
                                "name": response.prefill.name??'-',
                                "email": response.prefill.email,
                                "contact": response.prefill.contact??'-',
                            },
                            "theme": {
                                "color": "#F37254"
                            }
                        };
                        
                        var rzp1 = new Razorpay(options);
                        rzp1.open();
                    },
                    error: function(xhr) {
                        if(xhr.status === 401) {
                            window.location.href = xhr.responseJSON.redirect_url;
                        } else {
                            alert('Something went wrong. Please try again.');
                        }
                    }
                });
                });

// Flutterwave

$('#payment-form').on('submit', function(e) {
    if (document.getElementById('payment-method').value !== 'flutterwave') {
        return true;
    }

    e.preventDefault();

    $.ajax({
        url: $(this).attr('action'),
        type: "POST",
        data: $(this).serialize(),
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.status === 'success') {
                const config = response.data;
                FlutterwaveCheckout({
                    public_key: config.public_key,
                    tx_ref: config.tx_ref,
                    amount: config.amount,
                    currency: config.currency,
                    payment_options: config.payment_options,
                    customer: {
                        email: config.customer.email,
                        name: config.customer.name,
                        phone_number: config.customer.phonenumber
                    },
                    customizations: config.customizations,
                    callback: function(response) {
                        if (response.status === "successful") {
                            window.location.href = config.redirect_url + 
                                '&transaction_id=' + response.transaction_id + 
                                '&tx_ref=' + response.tx_ref + 
                                '&plan_id=' + config.meta.plan_id;
                        } else {
                            alert('Payment failed. Please try again.');
                        }
                    },
                    onclose: function() {
                        // Handle when customer closes the payment modal
                    }
                });
            } else {
                alert(response.message || 'Payment initialization failed');
            }
        },
        error: function(xhr) {
            const errorMsg = xhr.responseJSON?.message || 'Something went wrong. Please try again.';
            alert(errorMsg);
        }
    });
});
});
    
</script>
