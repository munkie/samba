Samba PHP stream wrapper
=====
[![Build Status](https://secure.travis-ci.org/munkie/samba.png?branch=master)](http://travis-ci.org/crystalservice/samba)
[![Coverage Status](https://coveralls.io/repos/munkie/samba/badge.png)](https://coveralls.io/r/crystalservice/samba)
[![Code Climate](https://codeclimate.com/github/munkie/samba.png)](https://codeclimate.com/github/crystalservice/samba)

Fork of [SMB4PHP](https://code.google.com/p/smbwebclient/)
 
Requirements
------

**smbclient** should be installed (use `sudo apt-get install smbclient` on ubuntu)  

Installation
-----

Add following to your _composer.json_ file:

```json
{
    "require": {
        "munkie/samba": "~1.0"
    },
}
```

Usage
-----

Register samba stream wrapper:
```php
\Samba\SambaStreamWrapper::register();
```

You can check if wrapper is already registered using this call:
```php
\Samba\SambaStreamWrapper::is_registered();
```

