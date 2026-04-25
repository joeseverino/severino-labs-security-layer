# Severino Labs Security Layer

A comprehensive WordPress security plugin that provides enterprise-grade protection through hardening, file integrity monitoring, security event logging, and passkey authentication.

## Features

### 🔒 Security Hardening
- XML-RPC protection
- Pingbacks blocking
- REST API user enumeration prevention
- Author enumeration blocking
- WordPress generator tag removal
- Unused endpoints blocking
- Custom error pages

### 📁 File Integrity Monitoring (FIM)
- SHA-256 baseline creation
- Automated integrity checks
- Configurable file monitoring targets
- Excluded paths management
- Real-time change detection
- Scheduled daily scans

### 🛡️ Security Event Monitoring (SEM)
- Request logging and analysis
- Blocked attack detection
- IP-based threat tracking
- Event type categorization
- Historical event storage

### 🔐 Passkey Authentication
- Custom login page design
- Configurable branding
- Enhanced security UI
- Logo and label customization

## Installation

1. Download the plugin files
2. Upload to your `/wp-content/plugins/` directory
3. Activate "Severino Labs Security Layer" through the WordPress admin
4. Configure settings in **Severino Security → Settings**

## Configuration

### Security Controls
Enable/disable individual security features based on your needs:
- Core hardening controls (recommended to keep enabled)
- Optional features like CSP headers and passkey login

### Branding Configuration
Customize the passkey login experience:
- Upload custom logo
- Set site display name
- Personalize the authentication flow

### File Integrity Monitoring
Configure what files to monitor:
- **Monitored Targets**: Add paths to critical files/directories
- **Excluded Paths**: Define paths to ignore (logs, caches, uploads)

## Dashboard

The admin dashboard provides:
- **Security Score**: Overall health indicator (0-100%)
- **Status Overview**: FIM automation, event monitoring, baseline status
- **Quick Actions**: Run checks, create baselines, access reports
- **Recent Events**: Latest security activity
- **System Information**: Plugin and environment details

## Security Score Calculation

The security score is calculated based on:
- Plugin active: +20 points
- FIM enabled: +25 points
- Baseline created: +15 points
- FIM check passed: +10 points
- SEM enabled: +20 points
- No events today: +10 points

## Requirements

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+

## Security Best Practices

1. **Keep core hardening enabled** - These are baseline protections
2. **Create FIM baseline** after initial setup and after major updates
3. **Monitor security events** regularly for suspicious activity
4. **Configure exclusions** for cache directories and temporary files
5. **Enable scheduled checks** for automated monitoring

## Support

For support and feature requests, please visit the [GitHub repository](https://github.com/yourusername/severino-labs-security-layer).

## Changelog

### Version 5.1.2
- Complete dashboard redesign with security scoring
- Modular settings configuration
- Enhanced UI with professional styling
- Configurable branding and FIM targets
- Improved responsive design

### Version 5.1.1
- Added modular constants and settings system
- Implemented configurable FIM targets and exclusions
- Enhanced passkey login with branding support

### Version 5.1.0
- Initial release with core security features
- File integrity monitoring
- Security event logging
- Passkey authentication

## License

This plugin is licensed under the GPL v2 or later.

## Contributing

Contributions are welcome! Please feel free to submit pull requests or open issues on GitHub.

## Author

**Joe Severino**
- Website: [jseverino.com](https://jseverino.com)
- GitHub: [@yourusername](https://github.com/yourusername)

---

*Enterprise-grade WordPress security made simple.*