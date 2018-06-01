<?php


/*
	Conversions:
	- VARIABLES -
	{$var}     ---->     <?php echo $var; ?>
	- IF -
	{if(condition)}	    ---->	<?php if(condition): ?>
	{elseif(condition)} ---->	<?php elseif(condition): ?>
	{else} 		    ---->	<?php else: ?>
	{/if}		    ---->	<?php endif; ?>
	- WHILE -
	{while(condition)}  ---->	<?php while(condition): ?>
	{/while}	    ---->	<?php endwhile; ?>
	- FOR -
	{for(expr1; expr2; expr3)}  ---->	<?php for(expr1; expr2; expr3): ?>
	{/for}			    ---->	<?php endfor; ?>
	- FOREACH -
	{foreach(array as key => value)}    ---->	<?php foreach(array as key => value): ?>
	{/foreach}			    ---->	<?php endforeach; ?>
	- BREAK & CONTINUE -
	{break}     ---->	<?php break; ?>
	{continue}  ---->	<?php continue; ?>
	- SWITCH -
	{switch(var)}       ---->	<?php switch(var): ?>
	{case condition}    ---->	<?php case condition: ?>
	{default}	    ---->	<?php default: ?>
	{/switch}	    ---->	<?php endswitch; ?>
	- INCLUDE -
	{include ...}     ---->     <?php echo file_get_contents(...); ?>
	example: {include "/templates/footer.php"} -> <?php echo file_get_contents("/templates/footer.php"); ?>
	example: {include $file}                   -> <?php echo file_get_contents("$file"); ?>
	- EXPRESSIONS -
	{@expr}     ---->     <?php expr; ?>
	Shouldn't be used to much in templates!
	example: {@$i++}                -> <?php $i++; ?>
	example: {@event()}             -> <?php event(); ?>
	example: {@echo ucfirst($var)}  -> <?php echo ucfirst($var); ?>
	- COMMENTS -
	{* comment text *}     ---->     <!-- comment text -->
	Also works for multiline!
	That's it.
*/


class Simplates {
	private static $conversions = [
		'/\{ *((?:if|elseif|while|for|foreach|switch) *\(.*?\)) *\}/' => '<?php $1: ?>',
		/*  All {...(...)} tags */

		'/\{ *\/ *(if|while|for|foreach|switch) *\}/'                 => '<?php end$1; ?>',
		/* All {/...} tags */

		'/\{ *(case .+?) *\}/'                                        => '<?php $1: ?>',
		/* Case tag */

		'/\{ *(else|default) *\}/'                                    => '<?php $1: ?>',
		/* All {...} tags which convert to <?php ...: ?> */

		'/\{ *(break|continue) *\}/'                                  => '<?php $1; ?>',
		/* All {...} tags which convert to <?php ...; ?> */

		'/\{ *(\$[^ ]+?) *\}/'                                        => '<?php echo $1; ?>',
		/* All {$...} tags (Variables) */

		'/\{ *\@ *(.*?) *\}/'                                         => '<?php $1; ?>',
		/* All {@...} tags (Expressions) */

		'/\{ *include *(.*?) *\}/'                                    => '<?php echo file_get_contents($1); ?>',
		/* Include tag */

		'/\{\*(.+?)\*\}/s'                                            => '<!--$1-->'
		/* Comments */
	];

	private static $regex, $replacement;

	private static function init () {
		if (!self::$regex || !self::$replacement) {
			// Split all conversions into their own array
			self::$regex = array_keys(self::$conversions);
			self::$replacement = array_values(self::$conversions);
		}
	}

	public static function convert ($input_file, $output_file = false) {
		self::init();

		if (is_array($input_file)) {
			// If input_file is an array, redirect to method multiple_convert
			return self::multiple_convert($input_file, $output_file);
		}

		if (!file_exists($input_file)) {
			// If input file doesn't exist, throw an error
			trigger_error('Input file, ' . $input_file . ', does not exist.', E_USER_ERROR);
		}

		// Get input file contents
		$content = file_get_contents($input_file);

		// Replace all regular expressions with their replacement
		$converted = preg_replace(self::$regex, self::$replacement, $content);

		if (!$output_file) {
			// If no output file was set, just return the converted file data
			return $converted;
		} else {
			// Else write the converted file data to the output file
			file_put_contents($output_file, $converted);

			return true;
		}
	}

	private static function multiple_convert ($input_files, $output_files = false) {
		if (!$output_files || count($input_files) > count($output_files)) {
			// If there are no output files given, throw an error
			trigger_error('Each input file must have it\'s own output file.', E_USER_ERROR);
		}

		for ($i = 0; $i < count($input_files); $i++) {
			// Convert each file
			self::convert($input_files[$i], $output_files[$i]);
		}

		return true;
	}
}