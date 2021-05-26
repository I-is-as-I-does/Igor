
# Igor

Igor will auto-generate your interfaces files.
Specify a file or directory, and voilà!

Yes, *I know*. Usually, you write your interfaces first, and generate files *from* them.
But for odd cases or weird people, Igor is there.

## Getting Started

### Install

```bash
$ composer require ssitu/igor
```
### Ready for CLI

To uses Igor in CLI, install `ssitu/euclid` too.
Check out `IgorCli.php` in `src/`, and `bin/igor` in `samples/`.
```bash
$ php bin/igor
```

And then just follow the prompts!

## Methods

### Main

```php
$Igor->doALLtheInterfaces($srcdir, $intfdir);
$Igor->doOneInterface($srcfile, $dest);
```
### Setters

```php
$Igor->set_srcFilesGlobpattrn($pattern); // default: "*[!(_i)].php"
$Igor->set_intrFilesGlobpattrn($pattern); // default: none
$Igor->set_intrFilesSuffx($suffx); // default: "_i"
$Igor->set_intrFilesPrefx($prefx); // default: none

# Manually specify License mention:
$Igor->set_licenseMention($mention); // default: none
# Or let Igor locate it in source file and copy it:
$Igor->set_autoSearchLicense($bool); // default: true

$Igor->set_rewrite($bool); // default: true
/* Applies to method `doALLtheInterfaces`.
If set to false, Igor will skip source files that already have 
a matching interface file (but it does not check if existing interface is up-to-date!). 
Otherwise, an existing interface file will be rewritten from sratch. */

$Igor->set_addImplementsToSrc($bool); // default: true
/* If set to `true`: 
Igor will add 'implements InterfaceName' to source file, 
if not already set. */
```

## Contributing

Sure! You can take a loot at [CONTRIBUTING](CONTRIBUTING.md).

## License

This project is under the MIT License; cf. [LICENSE](LICENSE) for details.