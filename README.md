# wp-web-parser
An abstract web parser plugin for Wordpress. It works in tandem with child plugins, written with a specific web resource in mind. **Work in progress**.

## Usage
Although the name does not suggest so, this particular plugin does not do any parsing whatsoever. Instead it serves as a gateway for actual parsers to send their data to, after they're done gathering it. An example of a parser is in the `sample_parsers` directory.

## Installation
Put the `entropi-web-parser.php` file into the `wp-content/plugins` directory. After that install the actual parser[s] plugin[s].
