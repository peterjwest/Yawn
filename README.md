#Yawn#
##A lazy HTML parser for PHP##

Yawn is designed to be a very fast, lightweight HTML parser which also benefits from lazy evaluation. This means it only parses HTML as you need it.

Yawn isn't designed for HTML validation, in fact HTML validation requires complete parsing, which removes all the benefit from lazy evaluation.

If you want to validate HTML, I suggest using one of the many existing parsers like PHP's DOM or HTML Tidy.

##Issues##

- Can't parse text nodes at the end of the tree