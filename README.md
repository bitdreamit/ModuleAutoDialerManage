{
    "name": "bitdreamit/module-auto-dialer-manage",
    "description": "ModuleAutoDialerManage - Professional campaign dialer for MikoPBX with live dashboard, DNC blacklist, AMD, scheduling, webhooks, and REST API",
    "require": {
        "php": "7.4",
        "phpoffice/phpspreadsheet": "1.29.2",
        "cesargb/php-log-rotation": "2.5.0"
    },
    "autoload": {
        "psr-4": {
            "Modules\\ModuleAutoDialerManage\\": "/"
        }
    },
    "type": "application",
    "keywords": [
        "pbx",
        "asterisk",
        "freepbx",
        "mikopbx",
        "sip",
        "voip",
        "phalcon",
        "telephony",
        "uc",
        "auto-dialer",
        "campaign-dialer",
        "predictive-dialer",
        "call-center",
        "bitdreamit"
    ],
    "license": "GPL-3.0-or-later",
    "homepage": "https://github.com/bitdreamit/ModuleAutoDialerManage",
    "support": {
        "email": "support@bitdreamit.com",
        "issues": "https://github.com/bitdreamit/ModuleAutoDialerManage/issues",
        "source": "https://github.com/bitdreamit/ModuleAutoDialerManage"
    },
    "authors": [
        {
            "name": "Bit Dream IT",
            "email": "support@bitdreamit.com",
            "homepage": "https://bitdreamit.com",
            "role": "Maintainer"
        },
        {
            "name": "Alexey Portnov",
            "email": "apor@miko.ru",
            "homepage": "https://www.facebook.com/PortnovAlexey",
            "role": "Original author (upstream ModuleAutoDialer)"
        },
        {
            "name": "Nikolay Beketov",
            "email": "nbek@miko.ru",
            "homepage": "https://www.linkedin.com/in/nikolay-beketov",
            "role": "Original author (upstream ModuleAutoDialer)"
        }
    ],
    "funding": [
        {
            "type": "github",
            "url": "https://github.com/sponsors/bitdreamit"
        }
    ],
    "config": {
        "sort-packages": true,
        "platform": {
            "php": "7.4"
        }
    }
}
