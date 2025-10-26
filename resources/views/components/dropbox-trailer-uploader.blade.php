<div class="dropbox-uploader-container">
    <div class="mb-3">
        <label class="form-label">{{ __('Upload Video to Dropbox') }}</label>
        <div class="input-group">
            <input type="file" 
                   class="form-control" 
                   id="dropbox_video_file" 
                   accept="video/*" 
                   onchange="handleTrailerFileSelect(this)">
            <button type="button" 
                    class="btn btn-primary" 
                    id="upload_trailer_to_dropbox_btn" 
                    onclick="uploadTrailerToDropbox()" 
                    disabled>
                <i class="ph ph-upload"></i> {{ __('Upload to Dropbox') }}
            </button>
        </div>
        <div class="form-text">{{ __('Select a video file and click upload to store it in Dropbox') }}</div>
        <!-- <button type="button" class="btn btn-sm btn-outline-info mt-2" onclick="testDropboxConnection()">
            <i class="ph ph-connection"></i> {{ __('Test Connection') }}
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary mt-2 ms-2" onclick="checkDropboxConfig()">
            <i class="ph ph-gear"></i> {{ __('Check Config') }}
        </button>
        <button type="button" class="btn btn-sm btn-outline-warning mt-2 ms-2" onclick="testFilenameGeneration()">
            <i class="ph ph-file-text"></i> {{ __('Test Filename') }}
        </button> -->
        @if(session('success'))
            <div style="color:green">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div style="color:red">{{ session('error') }}</div>
        @endif

        <!-- <a href="{{ url('/app/entertainments/dropbox/connect') }}">Connect Dropbox</a>
        <a href="{{ url('/app/entertainments/dropbox/refresh') }}">Refresh Dropbox Token</a> -->
    </div>

    <div id="trailer_upload_progress" class="progress mb-3" style="display: none;">
        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
    </div>

    <div id="trailer_upload_status" class="alert" style="display: none;"></div>
    <!-- <a href="https://dl.dropboxusercontent.com/s/deroi5nwm6u7gdf/advice.png" class="dropbox-saver"></a> -->

    <div id="dropbox_file_info" class="card" style="display: none;">
        <div class="card-body">
            <h6 class="card-title">{{ __('Uploaded File Information') }}</h6>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>{{ __('File Name') }}:</strong> <span id="dropbox_file_name"></span></p>
                    <p><strong>{{ __('File Size') }}:</strong> <span id="dropbox_file_size"></span></p>
                </div>
                <div class="col-md-6">
                    <p><strong>{{ __('Dropbox URL') }}:</strong> <span id="dropbox_trailer_file_url"></span></p>
                    <p><strong>{{ __('Upload Date') }}:</strong> <span id="dropbox_upload_date"></span></p>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyDropboxUrl()">
                <i class="ph ph-copy"></i> {{ __('Copy URL') }}
            </button>
        </div>
    </div>
</div>
<!-- <script type="text/javascript" src="https://www.dropbox.com/static/api/2/dropins.js" id="dropboxjs" data-app-key="ibcgqkhqzkg4mtw"></script> -->

<script>
let selectedTrailerFile = null;
let dropboxTrailerUploadInProgress = false;



function handleTrailerFileSelect(input) {
    const file = input.files[0];
    const uploadBtn = document.getElementById('upload_trailer_to_dropbox_btn');
    
    if (file) {
        // Validate file type
        if (!file.type.startsWith('video/')) {
            showTrailerUploadStatus('Please select a valid video file.', 'danger');
            input.value = '';
            uploadBtn.disabled = true;
            return;
        }
        
        // Validate file size (max 2GB)
        const maxSize = 2 * 1024 * 1024 * 1024; // 2GB in bytes
        if (file.size > maxSize) {
            showTrailerUploadStatus('File size must be less than 2GB.', 'danger');
            input.value = '';
            uploadBtn.disabled = true;
            return;
        }
        
        selectedTrailerFile = file;
        uploadBtn.disabled = false;
        hideUploadStatus();
    //     var options = {
    // files: [
    //             // You can specify up to 100 files.
    //             {'url': file, 'filename': file.name},
    //             // ...
    //         ],

    //         // Success is called once all files have been successfully added to the user's
    //         // Dropbox, although they may not have synced to the user's devices yet.
    //         success: function () {
    //             // Indicate to the user that the files have been saved.
    //             alert("Success! Files saved to your Dropbox.");
    //         },

    //         // Progress is called periodically to update the application on the progress
    //         // of the user's downloads. The value passed to this callback is a float
    //         // between 0 and 1. The progress callback is guaranteed to be called at least
    //         // once with the value 1.
    //         progress: function (progress) {},

    //         // Cancel is called if the user presses the Cancel button or closes the Saver.
    //         cancel: function () {},

    //         // Error is called in the event of an unexpected response from the server
    //         // hosting the files, such as not being able to find a file. This callback is
    //         // also called if there is an error on Dropbox or if the user is over quota.
    //         error: function (errorMessage) {}
    //     };
    //     Dropbox.createSaveButton(file, file.name, options);

        
        // Show file info
        document.getElementById('dropbox_file_name').textContent = file.name;
        document.getElementById('dropbox_file_size').textContent = formatFileSize(file.size);
    } else {
        selectedTrailerFile = null;
        uploadBtn.disabled = true;
    }
}

function uploadTrailerToDropbox() {
    if (!selectedTrailerFile || dropboxTrailerUploadInProgress) {
        return;
    }
    
    dropboxTrailerUploadInProgress = true;
    const uploadBtn = document.getElementById('upload_trailer_to_dropbox_btn');
    const progressBar = document.querySelector('#trailer_upload_progress .progress-bar');
    
    // Show progress
    document.getElementById('trailer_upload_progress').style.display = 'block';
    progressBar.style.width = '0%';
    progressBar.textContent = '0%';
    
    // Disable upload button
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> {{ __("Uploading...") }}';
    
    // Create FormData
    const formData = new FormData();
    formData.append('video_file', selectedTrailerFile);
    formData.append('_token', '{{ csrf_token() }}');
    
    // Upload to server
    fetch('{{ route("backend.entertainments.upload-to-dropbox") }}', {
        method: 'POST',
        body: formData,
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
    .then(async (response) => {
        const contentType = response.headers.get('content-type') || '';
        let parsed;
        try {
            if (contentType.includes('application/json')) {
                parsed = await response.json();
            } else {
                const text = await response.text();
                parsed = JSON.parse(text);
            }
        } catch (e) {
            // Likely received an HTML error page (e.g., 419/500). Surface a concise message.
            const hint = (typeof window !== 'undefined') ? ' Unexpected non-JSON response (possibly a login redirect or error page).' : '';
            throw new Error('Upload error: response was not valid JSON.' + hint);
        }

        if (!response.ok) {
            const message = parsed && parsed.message ? parsed.message : 'Upload failed. Please try again.';
            throw new Error(message);
        }
        return parsed;
    })
    .then(data => {
        if (data.success) {
            showTrailerUploadStatus('Video uploaded to Dropbox successfully!', 'success');
            
            // Update hidden input with Dropbox URL
            document.getElementById('dropbox_video_url_input').value = data.dropbox_url;
            
            // Show file info
            document.getElementById('dropbox_trailer_file_url').textContent = data.dropbox_url;
            document.getElementById('dropbox_upload_date').textContent = new Date().toLocaleString();
            document.getElementById('dropbox_file_info').style.display = 'block';
            document.getElementById('dropbox_trailer_url').value = data.dropbox_url;
            // document.getElementById('trailer_url').value = data.hls_file;
            
            // Update progress to 100%
            progressBar.style.width = '100%';
            progressBar.textContent = '100%';
        } else {
            showTrailerUploadStatus(data.message || 'Upload failed. Please try again.', 'danger');
        }
    })
    .catch(error => {
        console.error('Upload error:', error + " dropbox-uploader.blade.php");
        showTrailerUploadStatus('Upload failed. Please check your connection and try again.', 'danger');
    })
    .finally(() => {
        dropboxTrailerUploadInProgress = false;
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = '<i class="ph ph-upload"></i> {{ __("Upload to Dropbox") }}';
        
        // Hide progress after a delay
        setTimeout(() => {
            document.getElementById('trailer_upload_progress').style.display = 'none';
        }, 2000);
    });
}

function showTrailerUploadStatus(message, type) {
    const statusDiv = document.getElementById('trailer_upload_status');
    statusDiv.className = `alert alert-${type}`;
    statusDiv.textContent = message;
    statusDiv.style.display = 'block';
}

function hideUploadStatus() {
    document.getElementById('trailer_upload_status').style.display = 'none';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function copyDropboxUrl() {
    const url = document.getElementById('dropbox_trailer_file_url').textContent;
    navigator.clipboard.writeText(url).then(() => {
        // Show temporary success message
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-check"></i> {{ __("Copied!") }}';
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-success');
        
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-primary');
        }, 2000);
    });
}

// Test Dropbox connection
function testDropboxConnection() {
    const testBtn = event.target;
    const originalText = testBtn.innerHTML;
    
    testBtn.disabled = true;
    testBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> {{ __("Testing...") }}';
    
    fetch('{{ route("backend.entertainments.test-dropbox-connection") }}', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showTrailerUploadStatus('Dropbox connection successful! Account: ' + data.account.name, 'success');
        } else {
            showTrailerUploadStatus('Dropbox connection failed: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Connection test error:', error);
        showTrailerUploadStatus('Connection test failed. Please check your settings.', 'danger');
    })
    .finally(() => {
        testBtn.disabled = false;
        testBtn.innerHTML = originalText;
    });
}

// Check Dropbox configuration
function checkDropboxConfig() {
    const configBtn = event.target;
    const originalText = configBtn.innerHTML;
    
    configBtn.disabled = true;
    configBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> {{ __("Checking...") }}';
    
    fetch('{{ route("backend.entertainments.dropbox-config-status") }}', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const config = data.config;
            let message = 'Configuration Status:\n';
            message += `• Configured: ${config.is_configured ? '✅' : '❌'}\n`;
            message += `• Token Format: ${config.token_format_valid ? '✅' : '❌'}\n`;
            message += `• App Key: ${config.config_vars.app_key ? '✅' : '❌'}\n`;
            message += `• App Secret: ${config.config_vars.app_secret ? '✅' : '❌'}\n`;
            message += `• Access Token: ${config.config_vars.access_token ? '✅' : '❌'}\n`;
            message += `• Redirect URI: ${config.config_vars.redirect_uri ? '✅' : '❌'}`;
            
            showTrailerUploadStatus(message, config.is_configured && config.token_format_valid ? 'success' : 'warning');
        } else {
            showTrailerUploadStatus('Configuration check failed: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Configuration check error:', error);
        showTrailerUploadStatus('Configuration check failed. Please check your settings.', 'danger');
    })
    .finally(() => {
        configBtn.disabled = false;
        configBtn.innerHTML = originalText;
    });
}

// Test filename generation
function testFilenameGeneration() {
    const testBtn = event.target;
    const originalText = testBtn.innerHTML;
    
    testBtn.disabled = true;
    testBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> {{ __("Testing...") }}';
    
    // Test with a sample filename that might have special characters
    const testFilename = 'Test Video with Special Chars!@#$%^&*()_+{}|:"<>?[]\\;\',./.mp4';
    
    fetch('{{ route("backend.entertainments.test-filename-generation", ["filename" => "test"]) }}'.replace('test', encodeURIComponent(testFilename)), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let message = 'Filename Generation Test:\n';
            message += `• Original: ${data.original}\n`;
            message += `• Generated: ${data.generated}\n`;
            message += `• UTF-8 Valid: ${data.is_utf8 ? '✅' : '❌'}\n`;
            message += `• Length: ${data.length}\n`;
            message += `• Safe for Filesystem: ${data.safe_for_filesystem ? '✅' : '❌'}`;
            
            showTrailerUploadStatus(message, 'info');
        } else {
            showTrailerUploadStatus('Filename test failed: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Filename test error:', error);
        showTrailerUploadStatus('Filename test failed. Please check your settings.', 'danger');
    })
    .finally(() => {
        testBtn.disabled = false;
        testBtn.innerHTML = originalText;
    });
}

// Initialize dropbox uploader
window.initDropboxUploader = function() {
    // Reset any existing state
    selectedTrailerFile = null;
    dropboxTrailerUploadInProgress = false;
    
    // Reset form elements
    document.getElementById('dropbox_video_file').value = '';
    document.getElementById('upload_trailer_to_dropbox_btn').disabled = true;
    document.getElementById('trailer_upload_progress').style.display = 'none';
    document.getElementById('trailer_upload_status').style.display = 'none';
    document.getElementById('dropbox_file_info').style.display = 'none';
    document.getElementById('dropbox_video_url_input').value = '';
};
</script>
