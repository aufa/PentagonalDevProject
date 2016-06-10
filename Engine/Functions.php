<?php
/**
 * Determines if the current version of PHP is equal to or greater than the supplied value
 *
 * @param	string
 * @return	bool	true if the current version is $version or higher
 */
function is_php($version)
{
    static $_is_php;
    $version = (string) $version;

    if ( ! isset($_is_php[$version]))
    {
        $_is_php[$version] = version_compare(PHP_VERSION, $version, '>=');
    }

    return $_is_php[$version];
}
/**
 * Tests for file writability
 *
 * is_writable() returns true on Windows servers when you really can't write to
 * the file, based on the read-only attribute. is_writable() is also unreliable
 * on Unix servers if safe_mode is on.
 *
 * @link	https://bugs.php.net/bug.php?id=54709
 * @param	string
 * @return	bool
 */
function is_really_writable($file)
{
    // If we're on a Unix server with safe_mode off we call is_writable
    if (DIRECTORY_SEPARATOR === '/' && (is_php('5.4') OR ! ini_get('safe_mode')))
    {
        return is_writable($file);
    }

    /* For Windows servers and safe_mode "on" installations we'll actually
     * write a file then read it. Bah...
     */
    if (is_dir($file))
    {
        $file = rtrim($file, '/').'/'.md5(mt_rand());
        if (($fp = @fopen($file, 'ab')) === false)
        {
            return false;
        }

        fclose($fp);
        @chmod($file, 0777);
        @unlink($file);
        return true;
    }
    elseif ( ! is_file($file) OR ($fp = @fopen($file, 'ab')) === false)
    {
        return false;
    }

    fclose($fp);
    return true;
}

/**
 * Class registry
 *
 * This function acts as a singleton. If the requested class does not
 * exist it is instantiated and set to a static variable. If it has
 * previously been instantiated the variable is returned.
 *
 * @param	string	$class     the class name being requested
 * @param	string	$directory the directory where the class should be found
 * @param	string	$param     optional argument to pass to the class constructor
 * @return	object
 */
function &load_class($class, $directory = 'libraries', $param = null)
{
    static $_classes = array();

    // Does the class exist? If so, we're done...
    if (isset($_classes[$class])) {
        return $_classes[$class];
    }

    $name = false;

    // Look for the class first in the local resource/libraries folder
    // then in the native system/libraries folder
    foreach (array(APPPATH, BASEPATH) as $path)
    {
        if (file_exists($path.$directory.'/'.$class.'.php')) {
            $name = 'CI_'.$class;
            if (class_exists($name, false) === false) {
                require_once($path.$directory.'/'.$class.'.php');
            }

            break;
        }
        if (file_exists($path.ucfirst($directory).'/'.$class.'.php')) {
            $name = 'CI_'.$class;

            if (class_exists($name, false) === false)
            {
                require_once($path.ucfirst($directory).'/'.$class.'.php');
            }

            break;
        }
    }

    if (config_item('subclass_prefix')) {
        // Is the request a class extension? If so we load it too
        if (file_exists(APPPATH . $directory . '/' . config_item('subclass_prefix') . $class . '.php')) {
            $name = config_item('subclass_prefix') . $class;
            if (class_exists($name, false) === false) {
                require_once(APPPATH . $directory . '/' . $name . '.php');
            }
        } elseif (file_exists(APPPATH . ucfirst($directory) . '/' . config_item('subclass_prefix') . $class . '.php')) {
            $name = config_item('subclass_prefix') . $class;

            if (class_exists($name, false) === false) {
                require_once(APPPATH . ucfirst($directory) . '/' . $name . '.php');
            }
        }
    }
    // Did we find the class?
    if ($name === false) {
        // Note: We use exit() rather than show_error() in order to avoid a
        // self-referencing loop with the Exceptions class
        set_status_header(503);
        echo 'Unable to locate the specified class: '.$class.'.php';
        exit(5); // EXIT_UNK_CLASS
    }

    // Keep track of what we just loaded
    is_loaded($class);

    $_classes[$class] = isset($param)
        ? new $name($param)
        : new $name();


    return $_classes[$class];
}

/**
 * Keeps track of which libraries have been loaded. This function is
 * called by the load_class() function above
 *
 * @param	string
 * @return	array
 */
function &is_loaded($class = '')
{
    static $_is_loaded = array();

    if ($class !== '') {
        $_is_loaded[strtolower($class)] = $class;
    }

    return $_is_loaded;
}

/**
 * Loads the main config.php file
 *
 * This function lets us grab the config file even if the Config class
 * hasn't been instantiated yet
 *
 * @param	array
 * @return	array
 */
function &get_config(array $replace = array())
{
    if (!empty($replace)) {
        return Processor::instance()->setAppConfig($replace);
    }
    return Processor::config('app', null);
}


/**
 * Returns the specified config item
 *
 * @param	string
 * @return	mixed
 */
function config_item($item)
{
    if ($item === null) {
        return null;
    }
    return Processor::config('app', $item);
}

/**
 * Returns the MIME types array from config/mimes.php
 *
 * @return	array
 */
function &get_mimes()
{
    $mimes = (array) Processor::config('mime_type');
    return $mimes;
}

/**
 * Is HTTPS?
 *
 * Determines if the resource is accessed via an encrypted
 * (HTTPS) connection.
 *
 * @return	bool
 */
function is_https()
{
    static $retval;
    if (!isset($retval)) {
        $retval = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off'
            || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'
            || !empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off'
            ? true
            : false;
    }

    return $retval;
}

/**
 * Is CLI?
 *
 * Test to see if a request was made from the command line.
 *
 * @return 	bool
 */
function is_cli()
{
    return (PHP_SAPI === 'cli' || defined('STDIN'));
}

// ------------------------------------------------------------------------

/**
 * Error Handler
 *
 * This function lets us invoke the exception class and
 * display errors using the standard error template located
 * in resource/view/errors/error_general.php
 * This function will send the error page directly to the
 * browser and exit.
 *
 * @param	string
 * @param	int
 * @param	string
 * @return	void
 */
function show_error($message, $status_code = 500, $heading = 'An Error Was Encountered')
{
    $status_code = abs($status_code);
    if ($status_code < 100) {
        $exit_status = $status_code + 9; // 9 is EXIT__AUTO_MIN
        if ($exit_status > 125) // 125 is EXIT__AUTO_MAX
        {
            $exit_status = 1; // EXIT_ERROR
        }

        $status_code = 500;
    } else {
        $exit_status = 1; // EXIT_ERROR
    }

    $_error =& load_class('Exceptions', 'core');
    $retval = $_error->show_error($heading, $message, 'error_general', $status_code);
    if (!is_cli() && function_exists('get_instance')) {
        $output = load_class('Output', 'Core');
        $output->set_output($retval);
        $output->_display();
        exit($exit_status);
    } else {
        echo $retval;
    }
    exit($exit_status);
}

/**
 * 404 Page Handler
 *
 * This function is similar to the show_error() function above
 * However, instead of the standard error template it displays
 * 404 errors.
 *
 * @param	string
 * @param	bool
 * @return	void
 */
function show_404($page = '', $log_error = true)
{
    $_error =& load_class('Exceptions', 'core');
    $_error->show_404($page, $log_error);
    exit(4); // EXIT_UNKNOWN_FILE
}

/**
 * Error Logging Interface
 *
 * We use this as a simple mechanism to access the logging
 * class and send messages to be logged.
 *
 * @param	string $level   the error level: 'error', 'debug' or 'info'
 * @param	string $message the error message
 * @return	void
 */
function log_message($level, $message)
{
    static $_log;

    if ($_log === null) {
        // references cannot be directly assigned to static variables, so we use an array
        $_log[0] =& load_class('Log', 'core');
    }

    $_log[0]->write_log($level, $message);
}

/**
 * Set HTTP Status Header
 *
 * @param	int	$code the status code
 * @param	string
 * @return	void
 */
function set_status_header($code = 200, $text = '')
{
    if (is_cli()) {
        return;
    }

    if (empty($code) OR ! is_numeric($code)) {
        show_error('Status codes must be numeric', 500);
    }

    if (empty($text)) {
        is_int($code) OR $code = (int) $code;
        $stati = array(
            100	=> 'Continue',
            101	=> 'Switching Protocols',

            200	=> 'OK',
            201	=> 'Created',
            202	=> 'Accepted',
            203	=> 'Non-Authoritative Information',
            204	=> 'No Content',
            205	=> 'Reset Content',
            206	=> 'Partial Content',

            300	=> 'Multiple Choices',
            301	=> 'Moved Permanently',
            302	=> 'Found',
            303	=> 'See Other',
            304	=> 'Not Modified',
            305	=> 'Use Proxy',
            307	=> 'Temporary Redirect',

            400	=> 'Bad Request',
            401	=> 'Unauthorized',
            402	=> 'Payment Required',
            403	=> 'Forbidden',
            404	=> 'Not Found',
            405	=> 'Method Not Allowed',
            406	=> 'Not Acceptable',
            407	=> 'Proxy Authentication Required',
            408	=> 'Request Timeout',
            409	=> 'Conflict',
            410	=> 'Gone',
            411	=> 'Length Required',
            412	=> 'Precondition Failed',
            413	=> 'Request Entity Too Large',
            414	=> 'Request-URI Too Long',
            415	=> 'Unsupported Media Type',
            416	=> 'Requested Range Not Satisfiable',
            417	=> 'Expectation Failed',
            422	=> 'Unprocessable Entity',

            500	=> 'Internal Server Error',
            501	=> 'Not Implemented',
            502	=> 'Bad Gateway',
            503	=> 'Service Unavailable',
            504	=> 'Gateway Timeout',
            505	=> 'HTTP Version Not Supported'
        );

        if (isset($stati[$code])) {
            $text = $stati[$code];
        } else {
            show_error('No status text available. Please check your status code number or supply your own message text.', 500);
        }
    }

    if (strpos(PHP_SAPI, 'cgi') === 0) {
        header('Status: '.$code.' '.$text, true);
    } else {
        $server_protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
        header($server_protocol.' '.$code.' '.$text, true, $code);
    }
}


/**
 * Error Handler
 *
 * This is the custom error handler that is declared at the (relative)
 * top of CodeIgniter.php. The main reason we use this is to permit
 * PHP errors to be logged in our own log files since the user may
 * not have access to server logs. Since this function effectively
 * intercepts PHP errors, however, we also need to display errors
 * based on the current error_reporting level.
 * We do that with the use of a PHP error template.
 *
 * @param	int	$severity
 * @param	string	$message
 * @param	string	$filepath
 * @param	int	$line
 * @return	void
 */
function _error_handler($severity, $message, $filepath, $line)
{
    $is_error = (((E_ERROR | E_COMPILE_ERROR | E_CORE_ERROR | E_USER_ERROR) & $severity) === $severity);

    // When an error occurred, set the status header to '500 Internal Server Error'
    // to indicate to the client something went wrong.
    // This can't be done within the $_error->show_php_error method because
    // it is only called when the display_errors flag is set (which isn't usually
    // the case in a production environment) or when errors are ignored because
    // they are above the error_reporting threshold.
    if ($is_error) {
        set_status_header(500);
    }

    // Should we ignore the error? We'll get the current error_reporting
    // level and add its bits with the severity bits to find out.
    if (($severity & error_reporting()) !== $severity) {
        return;
    }

    $_error =& load_class('Exceptions', 'core');
    $_error->log_exception($severity, $message, $filepath, $line);

    // Should we display the error?
    if (str_ireplace(array('off', 'none', 'no', 'false', 'null'), '', ini_get('display_errors'))) {
        $_error->show_php_error($severity, $message, $filepath, $line);
    }

    // If the error is fatal, the execution of the script should be stopped because
    // errors can't be recovered from. Halting the script conforms with PHP's
    // default error handling. See http://www.php.net/manual/en/errorfunc.constants.php
    if ($is_error) {
        exit(1); // EXIT_ERROR
    }
}

/**
 * Exception Handler
 *
 * Sends uncaught exceptions to the logger and displays them
 * only if display_errors is On so that they don't show up in
 * production environments.
 *
 * @param	Exception	$exception
 * @return	void
 */
function _exception_handler($exception)
{
    $_error =& load_class('Exceptions', 'core');
    $_error->log_exception('error', 'Exception: '.$exception->getMessage(), $exception->getFile(), $exception->getLine());

    // Should we display the error?
    if (str_ireplace(array('off', 'none', 'no', 'false', 'null'), '', ini_get('display_errors')))
    {
        $_error->show_exception($exception);
    }

    exit(1); // EXIT_ERROR
}

/**
 * Shutdown Handler
 *
 * This is the shutdown handler that is declared at the top
 * of CodeIgniter.php. The main reason we use this is to simulate
 * a complete custom exception handler.
 *
 * E_STRICT is purposively neglected because such events may have
 * been caught. Duplication or none? None is preferred for now.
 *
 * @link	http://insomanic.me.uk/post/229851073/php-trick-catching-fatal-errors-e-error-with-a
 * @return	void
 */
function _shutdown_handler()
{
    $last_error = error_get_last();
    if (isset($last_error) &&
        ($last_error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING))
    ) {
        _error_handler($last_error['type'], $last_error['message'], $last_error['file'], $last_error['line']);
    }
}

/**
 * Remove Invisible Characters
 *
 * This prevents sandwiching null characters
 * between ascii characters, like Java\0script.
 *
 * @param	string
 * @param	bool
 * @return	string
 */
function remove_invisible_characters($str, $url_encoded = true)
{
    $non_displayables = array();

    // every control character except newline (dec 10),
    // carriage return (dec 13) and horizontal tab (dec 09)
    if ($url_encoded) {
        $non_displayables[] = '/%0[0-8bcef]/';	// url encoded 00-08, 11, 12, 14, 15
        $non_displayables[] = '/%1[0-9a-f]/';	// url encoded 16-31
    }

    $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';	// 00-08, 11, 12, 14-31, 127

    do {
        $str = preg_replace($non_displayables, '', $str, -1, $count);
    } while ($count);

    return $str;
}

/**
 * Returns HTML escaped variable.
 *
 * @param	mixed	$var		The input string or array of strings to be escaped.
 * @param	bool	$double_encode	$double_encode set to false prevents escaping twice.
 * @return	mixed			The escaped string or array of strings as a result.
 */
function html_escape($var, $double_encode = true)
{
    if (empty($var)) {
        return $var;
    }

    if (is_array($var)) {
        foreach (array_keys($var) as $key)
        {
            $var[$key] = html_escape($var[$key], $double_encode);
        }

        return $var;
    }

    return htmlspecialchars($var, ENT_QUOTES, config_item('charset'), $double_encode);
}

/**
 * Stringify attributes for use in HTML tags.
 *
 * Helper function used to convert a string, array, or object
 * of attributes to a string.
 *
 * @param	mixed	string, array, object
 * @param	bool
 * @return	string
 */
function _stringify_attributes($attributes, $js = false)
{
    $atts = NULL;

    if (empty($attributes)) {
        return $atts;
    }

    if (is_string($attributes)) {
        return ' '.$attributes;
    }

    $attributes = (array) $attributes;

    foreach ($attributes as $key => $val) {
        $atts .= ($js) ? $key.'='.$val.',' : ' '.$key.'="'.$val.'"';
    }

    return rtrim($atts, ',');
}

/**
 * Function usable
 *
 * Executes a function_exists() check, and if the Suhosin PHP
 * extension is loaded - checks whether the function that is
 * checked might be disabled in there as well.
 *
 * This is useful as function_exists() will return false for
 * functions disabled via the *disable_functions* php.ini
 * setting, but not for *suhosin.executor.func.blacklist* and
 * *suhosin.executor.disable_eval*. These settings will just
 * terminate script execution if a disabled function is executed.
 *
 * The above described behavior turned out to be a bug in Suhosin,
 * but even though a fix was commited for 0.9.34 on 2012-02-12,
 * that version is yet to be released. This function will therefore
 * be just temporary, but would probably be kept for a few years.
 *
 * @link	http://www.hardened-php.net/suhosin/
 * @param	string	$function_name	Function to check for
 * @return	bool	true if the function exists and is safe to call,
 *			false otherwise.
 */
function function_usable($function_name)
{
    static $_suhosin_func_blacklist;

    if (function_exists($function_name))
    {
        if ( ! isset($_suhosin_func_blacklist))
        {
            $_suhosin_func_blacklist = extension_loaded('suhosin')
                ? explode(',', trim(ini_get('suhosin.executor.func.blacklist')))
                : array();
        }

        return ! in_array($function_name, $_suhosin_func_blacklist, true);
    }

    return false;
}
