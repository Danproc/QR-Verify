# Verification QR Manager

A WordPress plugin for generating and managing unique QR codes with advanced features including batch codes, logo integration, and PDF generation.

## Version
1.1.13

## File Structure

```
Verification-QR-Manager/
├── verification-qr-manager.php    # Main plugin file - entry point
├── includes/                      # Core functionality modules
│   ├── database.php              # Database table creation and management
│   ├── admin-page.php             # WordPress admin interface
│   ├── qr-generator.php           # QR code generation with logos and batch codes
│   ├── qr-scanner.php             # QR code scan tracking functionality
│   ├── shortcodes.php             # WordPress shortcodes for frontend display
│   ├── download-handlers.php      # ZIP download functionality
│   └── pdf-generator.php          # PDF generation for sticker sheets
├── assets/                        # Static assets
│   ├── fonts/
│   │   └── Montserrat-Bold.ttf    # Font for batch code text on QR codes
│   └── style.css                  # Plugin styles
├── libs/                          # Third-party libraries
│   └── tc-lib-pdf-main/           # TCPDF library for PDF generation
└── phpqrcode/                     # QR code generation library
```

## Features

### Core Functionality
- **QR Code Generation**: Generate multiple QR codes with unique IDs
- **Batch Codes**: 8-character batch codes burned into QR code images
- **Logo Integration**: Optional logo overlay on QR codes
- **Scan Tracking**: Track scan counts and first scan timestamps
- **Category Management**: Organize QR codes by categories

### Admin Interface
- **Bulk Operations**: Select and manage multiple QR codes
- **Filtering**: Filter by batch code, category, and scan status
- **Sorting**: Order by scan count (ascending/descending)
- **Export Options**: Download as ZIP or PDF sticker sheets

### Frontend Integration
- **Shortcodes**: 
  - `[qr_scan_data]` - Display scan statistics
  - `[qr_batch_code]` - Show batch code for current QR
- **Automatic Scanning**: Track scans via URL parameters
- **Post Integration**: Link QR codes to custom post types (e.g., 'strain')

### PDF Generation
- **Sticker Sheets**: Generate professional PDF layouts for printing
- **Cut Contours**: Includes cutting guides for die-cutting
- **Custom Dimensions**: 700mm wide format with configurable layouts

## Database Schema

The plugin creates a table `wp_vqr_codes` with the following structure:

```sql
CREATE TABLE wp_vqr_codes (
    id               BIGINT(20) NOT NULL AUTO_INCREMENT,
    qr_key           VARCHAR(64) NOT NULL,
    qr_code          VARCHAR(255) NOT NULL,
    url              VARCHAR(255) NOT NULL,
    batch_code       VARCHAR(8) NOT NULL,
    category         VARCHAR(100) DEFAULT '',
    scan_count       INT DEFAULT 0,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    first_scanned_at DATETIME DEFAULT NULL,
    post_id          BIGINT(20) DEFAULT NULL,
    PRIMARY KEY (id)
);
```

## Usage

### Generating QR Codes
1. Navigate to **QR Codes** in WordPress admin
2. Fill in the generation form:
   - Number of codes to generate
   - Base URL for QR code links
   - Category for organization
   - 4-character prefix for batch codes
   - Optional logo file (PNG/JPEG)
   - Associated post/strain

### Scanning QR Codes
QR codes automatically track scans when accessed via the generated URLs. Scan data is available through:
- Admin interface statistics
- Shortcodes on frontend pages
- Post meta data for associated posts

### Bulk Operations
- **Select multiple QR codes** using checkboxes
- **Download as ZIP**: Get all QR code images
- **Generate PDF**: Create printable sticker sheets
- **Reset scan counts**: Clear tracking data
- **Delete**: Remove QR codes permanently

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through WordPress admin
3. Navigate to **QR Codes** in the admin menu
4. Start generating QR codes!

## Dependencies

- **WordPress**: 5.0+
- **PHP**: 7.4+
- **PHP Extensions**: GD library for image manipulation
- **Custom Post Types**: 'strain' post type (configurable)

## Technical Notes

### Security
- All user inputs are sanitized and validated
- WordPress nonces protect against CSRF attacks
- User capability checks ensure proper permissions

### Performance
- Efficient database queries with proper indexing
- Image processing optimized for batch generation
- PDF generation uses memory-efficient streaming

### Extensibility
- Modular architecture allows easy feature additions
- Hooks and filters for customization
- Clean separation of concerns

## Author
Dan Proctor  
Website: https://thenorthern-web.co.uk/

## License
This plugin is proprietary software. All rights reserved.
