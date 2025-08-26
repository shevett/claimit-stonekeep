# AWS S3 Setup Guide

This guide will help you configure AWS S3 functionality in your ClaimIt application.

## Prerequisites

1. **AWS Account**: You need an active AWS account
2. **S3 Bucket**: Create an S3 bucket in AWS Console
3. **AWS Credentials**: Access Key ID and Secret Access Key

## Step 1: Create AWS Credentials

### Option A: IAM User (Recommended)
1. Go to **AWS Console** → **IAM** → **Users**
2. Click **Add User**
3. Choose **Programmatic access**
4. Attach policy: **AmazonS3FullAccess** (or create custom policy for specific bucket)
5. Download the **Access Key ID** and **Secret Access Key**

### Option B: Temporary Credentials
Use AWS STS or IAM roles if you prefer temporary credentials.

## Step 2: Configure Application

1. **Copy credentials template**:
   ```bash
   cp config/aws-credentials.example.php config/aws-credentials.php
   ```

2. **Edit the credentials file**:
   ```php
   <?php
   return [
       'credentials' => [
           'key'    => 'YOUR_ACCESS_KEY_ID',
           'secret' => 'YOUR_SECRET_ACCESS_KEY',
           // Optional: for temporary credentials
           // 'token'  => 'YOUR_SESSION_TOKEN',
       ],
       'region' => 'us-east-1', // Your AWS region
       'version' => 'latest',
       
       's3' => [
           'bucket' => 'your-bucket-name',
           'prefix' => 'claimit/', // Optional: organize files
       ]
   ];
   ```

3. **Set correct values**:
   - `key`: Your AWS Access Key ID
   - `secret`: Your AWS Secret Access Key  
   - `region`: Your preferred AWS region (e.g., 'us-east-1', 'us-west-2', 'eu-west-1')
   - `bucket`: Your S3 bucket name
   - `prefix`: Optional prefix for organizing files in the bucket

## Step 3: Security Notes

✅ **Secure Configuration**:
- The `config/aws-credentials.php` file is automatically excluded from git
- Never commit AWS credentials to version control
- Use IAM policies to limit permissions to specific S3 buckets only

⚠️ **Important**:
- Keep your AWS credentials secure
- Rotate credentials regularly
- Use least-privilege principle for IAM policies

## Step 4: Test the Integration

1. **Access S3 page**: Visit `http://localhost:8000/?page=s3`
2. **Verify connection**: You should see bucket information
3. **Test functionality**:
   - List files in your bucket
   - Download files
   - Generate presigned URLs for sharing

## Features Available

### ✅ **List Assets**
- View all files in your S3 bucket
- Filter by prefix/folder
- See file sizes, modification dates, and storage classes

### ✅ **Download Files**
- Direct download through the web interface
- Proper MIME type handling
- Secure file streaming

### ✅ **Generate Presigned URLs**
- Create secure, temporary download links
- 1-hour expiration by default
- Share files without exposing credentials

### ✅ **Prefix Filtering**
- Organize files with prefixes (like folders)
- Filter view by specific prefixes
- Support for nested organization

## Example IAM Policy

For enhanced security, create a custom IAM policy:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject",
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::your-bucket-name",
                "arn:aws:s3:::your-bucket-name/*"
            ]
        }
    ]
}
```

## Troubleshooting

### Common Issues

1. **"AWS credentials not configured"**
   - Ensure `config/aws-credentials.php` exists
   - Check file permissions are readable by web server

2. **"Access Denied"**
   - Verify AWS credentials are correct
   - Check IAM policy has required S3 permissions
   - Confirm bucket name is correct

3. **"Region mismatch"**
   - Ensure region in config matches your bucket's region
   - Check bucket exists in the specified region

4. **"SSL/TLS errors"**
   - Ensure your server has up-to-date CA certificates
   - Check firewall allows HTTPS outbound connections

### Debug Mode

To enable debug logging, add this to your `config/config.php`:

```php
// Enable AWS debug logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/aws-debug.log');
```

## Next Steps

Once configured, you can:

1. **Upload files** to your S3 bucket via AWS Console
2. **Organize files** using prefixes (like folders)
3. **Use the web interface** to browse, download, and share files
4. **Integrate with claims** by linking S3 files to claim records

## Support

If you encounter issues:

1. Check the browser console for JavaScript errors
2. Review PHP error logs
3. Verify AWS credentials and permissions
4. Test bucket access using AWS CLI: `aws s3 ls s3://your-bucket-name` 