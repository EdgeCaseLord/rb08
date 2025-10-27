# PDF 502 Error Fix Guide

## Current Status
- ✅ Fixed excessive debug logging in PDF template
- ✅ Added retry mechanism (3 attempts)
- ✅ Increased memory limit to 1GB
- ✅ Increased timeout to 10 minutes
- ✅ Added comprehensive Chrome stability flags
- ✅ Added memory monitoring and garbage collection
- ✅ Added nginx buffering disable headers

## If 502 Errors Persist

### 1. Test with Simple Endpoint
Try the test endpoint first to isolate the issue:
```
GET /test-pdf/{book_id}
```
This generates a PDF with only 1 recipe to test if the issue is with:
- Server configuration
- Chrome/browsershot
- Template complexity

### 2. Check Server Configuration

#### Nginx Configuration
Add to your nginx site config:
```nginx
location ~* \.pdf$ {
    proxy_read_timeout 600s;
    proxy_connect_timeout 600s;
    proxy_send_timeout 600s;
    fastcgi_read_timeout 600s;
    fastcgi_send_timeout 600s;
}

location /book/ {
    proxy_read_timeout 600s;
    proxy_connect_timeout 600s;
    proxy_send_timeout 600s;
    fastcgi_read_timeout 600s;
    fastcgi_send_timeout 600s;
}
```

#### PHP-FPM Configuration
In `/etc/php/8.x/fpm/pool.d/www.conf`:
```ini
request_terminate_timeout = 600
max_execution_time = 600
memory_limit = 1G
```

#### Apache Configuration
In `.htaccess` or virtual host:
```apache
<IfModule mod_fcgid.c>
    FcgidIOTimeout 600
    FcgidConnectTimeout 600
    FcgidBusyTimeout 600
</IfModule>
```

### 3. Check System Resources
```bash
# Check memory usage
free -h

# Check disk space
df -h

# Check Chrome processes
ps aux | grep chrome

# Kill stuck Chrome processes
pkill -f chrome
```

### 4. Alternative Solutions

#### Option A: Queue-Based PDF Generation
Move PDF generation to a background job:
```php
// In BookPdfController
dispatch(new GenerateBookPdfJob($book))->onQueue('pdf-generation');
```

#### Option B: Chunked PDF Generation
Split large books into smaller PDFs and combine them.

#### Option C: Use Different PDF Engine
Consider switching from Browsershot to:
- DomPDF (simpler, faster)
- TCPDF
- mPDF

### 5. Monitoring Commands
```bash
# Monitor logs in real-time
tail -f storage/logs/laravel.log | grep -i "pdf\|book"

# Check server error logs
tail -f /var/log/nginx/error.log
tail -f /var/log/apache2/error.log

# Monitor memory usage
watch -n 1 'free -h && ps aux --sort=-%mem | head -10'
```

### 6. Debug Information
The enhanced logging now provides:
- Memory usage at each step
- Retry attempt details
- Chrome process information
- Server configuration details

Check logs for patterns:
- Memory exhaustion
- Chrome crashes
- Server timeouts
- Template rendering issues

## Next Steps
1. Test the `/test-pdf/{book_id}` endpoint
2. Check server configuration timeouts
3. Monitor system resources during PDF generation
4. Consider queue-based generation for large books
