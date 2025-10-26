# Dropbox Integration Setup Guide

This guide explains how to set up Dropbox integration for video uploads in the entertainment module.

## Prerequisites

1. A Dropbox account
2. Dropbox App credentials

## Step 1: Create a Dropbox App

1. Go to [Dropbox App Console](https://www.dropbox.com/developers/apps)
2. Click "Create app"
3. Choose "Scoped access"
4. Choose "Full Dropbox" access
5. Give your app a name (e.g., "StreamIt Video Uploader")
6. Click "Create app"

## Step 2: Configure App Permissions

1. In your app settings, go to "Permissions" tab
2. Enable the following permissions:
   - `files.content.write` - Upload files
   - `files.content.read` - Read files
   - `sharing.write` - Create shareable links
   - `files.metadata.read` - Read file metadata
3. Click "Submit" to save permissions

## Step 3: Generate Access Token

1. In your app settings, go to "Settings" tab
2. Under "OAuth 2", click "Generate" to create an access token
3. Copy the generated access token (you'll need this for the .env file)

## Step 4: Environment Configuration

Add the following variables to your `.env` file:

```env
# Dropbox Configuration
DROPBOX_APP_KEY=your_app_key_here
DROPBOX_APP_SECRET=your_app_secret_here
DROPBOX_ACCESS_TOKEN=your_access_token_here
DROPBOX_REDIRECT_URI=your_redirect_uri_here
```

## Step 5: Install Dependencies

Run the following command to install the Dropbox package:

```bash
composer install
```

## Step 6: Test Configuration

### Option 1: Using the Test Script
Run the test script from your project root:
```bash
php test_dropbox.php
```

### Option 2: Using the Web Interface
1. Go to the entertainment create form
2. Select "Dropbox" as the video upload type
3. Click the "Check Config" button to verify your configuration
4. Click the "Test Connection" button to test the API connection
5. If successful, try uploading a video file
6. Check if the file appears in your Dropbox account

## Troubleshooting

### Common Issues

1. **"Dropbox is not configured" error**
   - Check that all Dropbox environment variables are set in your .env file
   - Verify the access token is valid and not expired

2. **"Upload failed" error**
   - Check your server's upload limits in php.ini
   - Verify the video file format is supported
   - Check Dropbox API rate limits

3. **"Malformed UTF-8 characters" error**
   - This usually occurs when filenames contain special characters
   - The system now automatically cleans filenames to remove invalid characters
   - Ensure your video files have simple, ASCII-only filenames
   - Check that your server's locale settings support UTF-8

4. **"request body: expected null, got value" error**
   - This occurs when the Dropbox API endpoint doesn't expect a request body
   - The system now correctly sends null for endpoints that don't need a body
   - This has been fixed in the latest version

5. **File size too large**
   - The current limit is set to 2GB
   - You can modify this in the controller validation

### File Size Limits

- **PHP upload limit**: Check `upload_max_filesize` and `post_max_size` in php.ini
- **Dropbox limit**: 2GB per file (configurable)
- **Server timeout**: May need to increase `max_execution_time` for large files

## Security Considerations

1. **Access Token Security**
   - Never commit your .env file to version control
   - Use environment variables in production
   - Rotate access tokens regularly

2. **File Validation**
   - Only video files are accepted
   - File size is limited to prevent abuse
   - File type is validated on both client and server

3. **User Permissions**
   - Ensure only authorized users can upload videos
   - Consider implementing user-specific Dropbox folders

## API Rate Limits

Dropbox has the following rate limits:
- **Upload**: 300 requests per hour per app
- **Sharing**: 1000 requests per hour per app
- **Metadata**: 300 requests per hour per app

For high-volume applications, consider implementing rate limiting and queuing.

## Support

If you encounter issues:
1. Check the Laravel logs in `storage/logs/laravel.log`
2. Verify your Dropbox app permissions
3. Test with a small video file first
4. Check your server's error logs
