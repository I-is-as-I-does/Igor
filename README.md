
# Igor

Igor will auto-generate your interfaces files.
Specify a file or directory, and voilà!

Yes, *I know*. Usually, you write your interfaces first, and generate files *from* them.
But for odd cases or weird people, Igor is there.

## Getting Started

### Install

```bash
composer require ssitu/igor
```

### Ready for CLI

To uses Igor in CLI, install `ssitu/euclid` too.
Check out `IgorCli.php` in `src/`, and `bin/igor` in `samples/`.

```bash
php bin/igor
```

And then just follow the prompts!

## TLDR

```php
use SSITU\Igor\Igor;
require_once 'path/to/autoload.php';
$Igor = new Igor();
$Igor->set_intrNamespace('Castle\\MonseigneurNamespace')
     ->doALLtheInterfaces('classes/src/path/', 'interfaces/dest/path');
```

## Methods

### Main

```php
$Igor->doALLtheInterfaces($srcdir, $intfdir);
$Igor->doOneInterface($srcfile, $dest);
```

### Setters

All setters are chainable.

#### Files Details

```php
$Igor->set_srcFilesGlobpattrn($pattern); // default: "*[!(_i)].php"
$Igor->set_intrFilesGlobpattrn($pattern); // default: none
$Igor->set_intrFilesSuffx($suffx); // default: "_i"
$Igor->set_intrFilesPrefx($prefx); // default: none
```

#### License Mention

Manually specify License mention (1)  
Or let Igor locate it in source file and copy it (2)

```php
$Igor->set_licenseMention($mention);  // (1) default: none
$Igor->set_autoSearchLicense($bool); // (2) default: true
```

#### Namespace and Implementation

```php
$Igor->set_intrNamespace($intrNamespace); // default: ''
$Igor->set_addImplementsToSrc($bool); // default: true
$Igor->set_rewrite($bool); // default: true
```

Set interface namespace with `set_intrNamespace`.  
Namespace-guessing is too hazardous; better not breaking your code.

If `set_addImplementsToSrc` set to `true`:
Igor will add `implements \InterfaceNamespace\InterfaceName` to source file, *if* not already set.

`set_rewrite` applies to method `doALLtheInterfaces`.  
If set to false, Igor will skip source files that already have a matching interface file.  
Otherwise, said existing interface file will be rewritten from sratch.

## Contributing

Sure! You can take a loot at [CONTRIBUTING](CONTRIBUTING.md).

## License

This project is under the MIT License; cf. [LICENSE](LICENSE) for details.

![Igor](Igor.jpg)
