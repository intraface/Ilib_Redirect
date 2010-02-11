<?php
/**
 * Redirects a user to specific pages
 *
 *
 * Usage:
 *
 * On the page where the user starts to get into the redirect cycle (not necessary the
 * page the user returns to afterwards):
 *
 * <code>
 * // optional - variable sent in the url with id on redirect. Must be the same on sender and receiver pages.
 * $other_querystring_name        = '';
 * // optional
 * $other_return_querystring_name = '';
 *
 * $redirect = Redirect::go($session_id, $other_querystring_name, $other_return_querystring_name);
 *
 * $return_url      = 'http://http://example.dk/state.php/state.php?id=1';
 * $destination_url = 'http://example.dk/page.php';
 * $url = $redirect->setDestination($destination_url, $return_url);
 *
 * $parameter_to_return_with = 'add_contact_id'; // activates the parameter sent back to the return page
 * $how_many_parameters = ''; // could also be multiple if more parameters should be returned
 *
 * // optional method calls
 * $redirect->askParameter($parameter_to_return_with, [, 'multiple']);
 * // Identifier kan be set, if you have more redirects on the same page
 * // Makes it possible to return to the right redirect.
 * $redirect->setIdentifier('sted_1');
 *
 * // Doing the redirect
 * header('Location: '' . $url);
 * exit;
 * </code>
 *
 * On the page the user is sent to - and is later sent back to the previous page.
 *
 * <code>
 * // optional - variable sent in the url with id on redirect. Must be the same on sender and receiver pages.
 * $other_querystring_name        = '';
 * // optional
 * $other_return_querystring_name = '';
 *
 * // Must be called on every page show
 * $redirect = Redirect::receive($session_id, $other_querystring_name, $other_return_querystring_name = '';);
 *
 * if (isset($_POST['submit'])) {
 *     // save something
 *     // optional parameter
 *     $redirect->setParameter("add_contact_id", $added_id); // Denne s�tter parameter som skal sendes tilbage til siden. Den sendes dog kun tilbage hvis askParameter er sat ved opstart af redirect. Hvis ask er sat til multiple, s� gemmes der en ny hver gang den aktiveres, hvis ikke, overskrives den
 *
 *     // the redirect
 *     $standard_page_without_redirect = 'standard.php';
 *     header('Location: '.$redirect->getRedirect($standard_page_without_redirect));
 *     exit;
 * }
 *
 * <a href="<?php echo $redirect->getRedirect('standard.php'); ?>">Cancel</a>
 * </code>
 *
 * If you need to make a redirect which spans more redirects, like going from:
 *
 * first.php --> second.php --> third.php
 *
 * You can do the following (@todo ON WHICH PAGE?):
 *
 * <code>
 * if ($go_further) {
 * 	   $new_redireict = Redirect::go($session_id);
 * 	   $url = $new_redirect->setDestination('http://example.dk/first.php', 'http://example.dk/second.php?' . $redirect->get('redirect_query_string'));
 * 	   header('Location: ' . $url);
 *     exit;
 * }
 * </code>
 *
 * Notice that redirect_query_string has redirect_id=<id> on the page where redirect is set
 * (@todo WHICH PAGE IS THAT?).
 *
 * The final page of the redirect cycle (often the same page you started from) you can retrieve
 * the parameter again:
 *
 * <code>
 * if (isset($_GET['return_redirect_id'])) {
 *     $redirect = Redirect::return($session_id);
 *     // optional
 *     $redirect->getIdentifier(); returns the identifier set in the beginning
 *
 *     // retrieves the value - returns array if ask was 'multiple' else just the value
 *     $selected_values = $redirect->getParameter('add_contact_id');
 *
 *     // deletes the redirect, so that the action is not done again on the
 *     // use of Back button (@todo IS THIS OPTIONAL OR NECCESSARY)
 *     $redirect->delete();
 * }
 * </code>
 *
 * Notice:
 *
 * The system to automatically get redirect_id and return_redirect_id is based on $_GET variables.
 * If there is a need for $_POST write Sune Jensen <sj@sunet.dk>.
 *
 * For the time being it is possible to use:
 *
 * <code>
 * $redirect = new Redirect($session_id, $_POST['redirect_id|return_redirect_id']);
 * $redirect->reset();
 * </code>
 *
 * @package Intraface
 * @author  Sune Jensen <sj@sunet.dk>
 * @version @package-version@
 */
class Ilib_Redirect
{
    /**
     * @var string
     */
    private $session_id;

    /**
     * @var array
     */
    public $value;

    /**
     * @var array
     */
    private $querystring = array();

    /**
     * @var string
     */
    private $identifier;

    /**
     * @var array extra db condition string
     */
    private $extra_db_condition;

    /**
     * @var object database connection
     */
    private $db;

    function getRefererUrl()
    {
        if (!isset($_SERVER['HTTP_REFERER'])) {
            return false;
        }
        return $_SERVER['HTTP_REFERER'];
    }

    private function getRequestUri()
    {
        $url = explode('?', $_SERVER['REQUEST_URI']);
        return $url[0];
    }

    /**
     * Returns the uri to current file
     *
     * @access private (is public to be able to test it)
     *
     * @return string
     */
    public function thisUri()
    {
        if (!empty($_SERVER['HTTPS'])) {
            $protocol= 'https://';
        } else {
            $protocol = 'http://';
        }
        return $protocol. $_SERVER['HTTP_HOST'] . self::getRequestUri();
    }

    /**
     * Constructs a redirect object
     *
     * @param object  $session_id @todo THIS SHOULD BE SUBSTITUTED WITH SESSION_ID
     * @param integer $id     Id of the redirect
     *
     * @return object
     */
    public function __construct($session_id, $db, $id = 0, $options = array())
    {
        if (strlen($session_id) < 10) {
            trigger_error('the session id given as first parameter should be at least 10 characters long', E_USER_ERROR);
            exit;
        }

        if (!is_object($db)) {
            trigger_error('second parameter to Ilib_Redirect should be db object', E_USER_ERROR);
            exit;
        }
        $this->session_id = $session_id;
        $this->db = $db;
        $this->identifier = '';

        // notice: these is both parsed here and in factory
        $this->extra_db_condition['select'] = '';
        $this->extra_db_condition['insert'] = '';
        if (!empty($options['extra_db_condition'])) {
            if (is_string($options['extra_db_condition'])) $options['extra_db_condition'] = array($options['extra_db_condition']);
            if (count($options['extra_db_condition']) > 0) {
                $this->extra_db_condition['select'] = implode(' AND ', $options['extra_db_condition']).' AND ';
                $this->extra_db_condition['insert'] = implode(', ', $options['extra_db_condition']).', ';
            }
        }

        // notice: these are both parsed here and in factory
        $this->value['query_variable'] = 'redirect_id';
        if (!empty($options['query_variable'])) $this->value['query_variable'] = $options['query_variable'];
        $this->value['query_return_variable'] = 'return_redirect_id';
        if (!empty($options['query_return_variable'])) $this->value['query_return_variable'] = $options['query_return_variable'];

        $this->value['id'] = 0;

        $this->id = (int)$id;
        if ($this->id > 0) {
            $this->load();
        }
    }

    /**
     * Creates a redirect object on the go page
     *
     * @param object $session_id                @todo THIS SHOULD BE SUBSTITUTED WITH SESSION_ID
     * @param string $query_variable        @todo is this used for go?
     * @param string $query_return_variable @todo is this used for go?
     *
     * @return object
     */
    static function go($session_id, $db, $options = array())
    {
        return self::factory($session_id, $db, 'go', $options);
    }

    /**
     * Creates a redirect object on the receiving page
     *
     * @param object $session_id                @todo THIS SHOULD BE SUBSTITUTED WITH SESSION_ID
     * @param string $query_variable        EXPLAIN
     * @param string $query_return_variable EXPLAIN
     *
     * @return object
     */
    static function receive($session_id, $db, $options = array())
    {
        return self::factory($session_id, $db, 'receive', $options);
    }

    /**
     * Creates a redirect object on the returning page
     *
     * @param object $session_id                @todo THIS SHOULD BE SUBSTITUTED WITH SESSION_ID
     * @param string $query_variable        EXPLAIN
     * @param string $query_return_variable EXPLAIN
     *
     * @return object
     */
    static function returns($session_id, $db, $options = array())
    {
        return self::factory($session_id, $db, 'return', $options);
    }

    /**
     * Creates a redirect object
     *
     * This should be substituted with specific methods for the types
     *
     * @param object $session_id                @todo THIS SHOULD BE SUBSTITUTED WITH SESSION_ID
     * @param string $type                  Can be either go, receive or return
     *                                      WHAT IS THE DIFFERENCES
     * @param string $query_variable        EXPLAIN
     * @param string $query_return_variable EXPLAIN
     *
     * @return object
     */
    static function factory($session_id, $db, $type, $options = array())
    {

        if (strlen($session_id) < 10) {
            trigger_error('the session id given as first parameter should be at least 10 characters long', E_USER_ERROR);
            exit;
        }
        if (!is_object($db)) {
            trigger_error('second parameter to Ilib_Redirect should be db object', E_USER_ERROR);
            exit;
        }

        if (!in_array($type, array('go', 'receive', 'return'))) {
            trigger_error("Anden parameter i Redirect->factory er ikke enten 'go', 'receive' eller 'return'", E_USER_ERROR);
        }

        // notice: this is both parsed here and in constructor
        $extra_db_condition = '';
        if (!empty($options['extra_db_condition'])) {
            if (is_string($options['extra_db_condition'])) $options['extra_db_condition'] = array($options['extra_db_condition']);
            if (count($options['extra_db_condition']) > 0) {
                $extra_db_condition = implode(' AND ', $options['extra_db_condition']).' AND ';
            }
        }

        // notice: theese are both parsed here and in construnctor
        $query_variable = 'redirect_id';
        if (!empty($options['query_variable'])) {
            $query_variable = $options['query_variable'];
        }
        $query_return_variable = 'return_redirect_id';
        if (!empty($options['query_return_variable'])) {
            $query_return_variable = $options['query_return_variable'];
        }

        $reset = false;
        $id = 0;
        if ($type == 'go') {
            // Vi starter en ny redirect p� siden, derfor skal vi ikke her slette eksisterende redirects til denne side.
            $id = 0;
        } else {
            if (($type == 'receive' && isset($_GET[$query_variable]))) {
                // Vi modtager en redirect fra url'en. Derfor sletter vi alle andre redirects til denne side.
                $reset = true;
                $id = intval($_GET[$query_variable]);

            } elseif ($type == 'return' && isset($_GET[$query_return_variable])) {
                // Vi returnerer med en v�rdi. Der kan v�re en eksisterende redirect til denne side, som vi skal benytte igen. Vi sletter ikke andre redirects.
                $id = intval($_GET[$query_return_variable]);
            } elseif (self::getRefererUrl()) {
                // Vi arbejder inden for samme side. Vi finder forh�bentligt en redirect. Under alle omst�ndigheder sletter vi hvad vi ikke skal bruge.
                $reset = true;

                $url_parts = explode("?", self::getRefererUrl());
                // print("b");

                $this_uri = Ilib_Redirect::thisUri();

                // print($this_uri.' == '.$url_parts[0]);
                if ($this_uri == $url_parts[0]) {
                    // print("c");
                    // Vi arbejder inden for den samme side, s� finder vi id ud fra siden.

                    $result = $db->query("SELECT id FROM redirect WHERE session_id = ".$db->quote($session_id, 'text')." AND ".$extra_db_condition." destination_url = ".$db->quote($this_uri, 'text')." ORDER BY date_created DESC");
                    if (PEAR::isError($result)) {
                        trigger_error('Error in query: '.$result->getUserInfo(), E_USER_ERROR);
                        return false;
                    }
                    if ($row = $result->fetchRow(MDB2_FETCHMODE_ASSOC)) {
                        $id = $row['id'];
                    } else {
                        $id = 0;
                    }
                } else {
                    // print("d");
                    // Der er ikke sat et redirect_id, vi er ikke inden for samme side, s� m� det v�re et kald til siden som ikke benytter redirect. Vi sletter alle redirects til denne side.
                    $reset = true;
                    $id = 0;
                }
            }
        }

        $redirect = new Ilib_Redirect($session_id, $db, $id, $options);
        if ($reset) {
            $redirect->reset();
        }

        return $redirect;
    }

    /**
     * Sets a key - this could probably be removed
     *
     * @return void
     */
    private function set($key, $value)
    {
        if ($key != '') {
            $this->value[$key] = $value;
        } else {
            trigger_error("Key er ikke sat i Redirect->set", E_USER_ERROR);
        }
    }

    /**
     * Loads information about the redirect
     *
     * @return integer
     */
    private function load()
    {
        $sql = "SELECT * FROM redirect
            WHERE session_id = ".$this->db->quote($this->session_id, 'text')." AND
            ".$this->extra_db_condition['select']."
            id = ".$this->db->quote($this->id, 'integer');
        $result = $this->db->query($sql);
        if (PEAR::isError($result)) {
            trigger_error('Error in query: '.$result->getUserInfo(), E_USER_ERROR);
            return false;
        }

        if (!$row = $result->fetchRow(MDB2_FETCHMODE_ASSOC)) {
            $this->id = 0;
            $this->value['id'] = 0;
            return 0;
        }

        $this->value = array_merge($this->value, $row);
        $this->value['redirect_query_string'] = $this->get('query_variable')."=".$this->id;
        return $this->id;
    }

    /**
     * Parses an url and makes it save
     *
     * @todo actually add functionality
     *
     * @param string $url Url to parse
     *
     * @return string
     */
    private function parseUrl($url)
    {
        return $url;
    }

    /**
     * Sets an identifier
     *
     * @param string $identifier The identifier to use
     *
     * @return string
     */
    public function setIdentifier($identifier)
    {
        if ($this->id) {

            $result = $this->db->exec("UPDATE redirect SET identifier = ".$this->db->quote($identifier, 'text')." WHERE session_id = ".$this->db->quote($this->session_id, 'text')." AND ".$this->extra_db_condition['select']." id = ".$this->db->quote($this->id, 'integer'));
            if (PEAR::isError($result)) {
                trigger_error('Error in exec: '.$result->getUserInfo(), E_USER_ERROR);
                return false;
            }
            return true;
        } else {
            $this->identifier = $identifier;
            return true;
        }
    }

    /**
     * Set destination
     *
     * @param string $url        Destination url. The url redirect should work from
     * @param string $return_url Url to return to WHAT DOES THAT MEAN - GIVE A CODE EXAMPLE
     * @param string $cancel_url Url to redirect to if flow is cancelled
     *
     * @return string The url which should be used for the redirect
     */
    public function setDestination($destination_url, $return_url = '', $cancel_url = '')
    {
        /*
        if (!array_key_exists('SCRIPT_URI', $_SERVER)) {
            if (!empty($_SERVER['REQUEST_URI'])) {
                $_SERVER['SCRIPT_URI'] = $_SERVER['REQUEST_URI'];
            } else {
                trigger_error("Either SCRIPT_URI or REQUEST_URI is needed to be in _SERVER array", E_USER_ERROR);
                return false;
            }
        }
        */

        if (empty($return_url)) {
            $return_url = $this->parseUrl($this->thisUri());
        } else {
            $return_url = $this->parseUrl($return_url);
        }

        $destination_url = $this->parseUrl($destination_url);

        if (!$this->isUrlValid($destination_url)) {
            trigger_error("First parameter ($destination_url) in Redirect->setDestination needs to be the complete path", E_USER_ERROR);
            return false;
        }

        if (!$this->isUrlValid($return_url)) {
            trigger_error("Second parameter ($return_url) in Redirect->setDestination needs to be the complete path", E_USER_ERROR);
            return false;
        }

        if (!empty($cancel_url)) {
            $cancel_url = $this->parseUrl($cancel_url);
            if (!$this->isUrlValid($cancel_url)) {
                trigger_error("Third parameter in Redirect->setDestination needs to be the complete path", E_USER_ERROR);
                return false;
            }
        }

        // Only the clean url has to be save (no querystring), so it can be compared to $_SERVER['SCRIPT_URI'] later
        $url_parts = explode("?", $destination_url);

        //from_url = ".$this->db->quote($_SERVER['SCRIPT_URI'], 'text').",
        $result = $this->db->exec("INSERT INTO redirect
            SET
                from_url = ".$this->db->quote($this->thisUri(), 'text').",
                return_url = ".$this->db->quote($return_url, 'text').",
                destination_url = ".$this->db->quote($url_parts[0], 'text').",
                session_id = ".$this->db->quote($this->session_id, 'text').",
                cancel_url = ".$this->db->quote($cancel_url, 'text').",
                ".$this->extra_db_condition['insert']."
                identifier = ".$this->db->quote($this->identifier, 'text').",
                date_created = NOW()");
        if (PEAR::isError($result)) {
            trigger_error('Error in exec: '.$result->getUserInfo(), E_USER_ERROR);
            return false;
        }
        $id = $this->db->lastInsertId('redirect', 'id');
        if (PEAR::isError($id)) {
            trigger_error('Error in lastInsertId: '.$id->getUserInfo(), E_USER_ERROR);
            return false;
        }

        $this->id = $id;
        $this->load();

        $destination_url = $this->mergeQueryString($destination_url, $this->getRedirectQueryString());

        return $destination_url;
    }

    /**
     * Only redirect is performed if this url is the same as the url_destination in the
     * database.
     *
     * @param string $standard_location A fall back location if no redirect matches the one asked for
     *
     * @return string
     */
    public function getRedirect($standard_location)
    {
        if ($this->id > 0) {
            $this->addQuerystring($this->get('query_return_variable').'='.$this->id);
            return $this->mergeQuerystring($this->get('return_url'), $this->querystring);
        } else {
            return $standard_location;
        }
    }

    /**
     * Adds querystring to return_url
     *
     * @param string $querystring Querystring to add to the url
     *
     * @return void
     */
    private function addQueryString($querystring)
    {
        // if querystring already set, do not set it again
        if (in_array($querystring, $this->querystring) === false) {
            $this->querystring[] = $querystring;
        }
    }

    /**
     * Merges extra parameters on existing querystring with the right & or ?
     *
     * @param string $querystring
     * @param string $extra       Can be both a string or an array with parameter to add TO WHAT?
     *
     * @return string
     */
    private function mergeQueryString($querystring, $extra)
    {

        if (strstr($querystring, "?") === false) {
            $separator = "?";
        } else {
            $separator = '&';
        }

        if (is_array($extra) && count($extra) > 0) {
            return $querystring.$separator.implode('&', $extra);
        } elseif (is_string($extra) && $extra != "") {
            return $querystring.$separator.$extra;
        } else {
            return $querystring;
        }

    }

    /**
     * Resets old redirects
     *
     * @return boolean
     */
    private function reset()
    {
        if ($this->id == 0) {
            // @todo Kan de nu ogs� v�re rigtigt at den ikke kan slette hvor id er 0!
            // trigger_error("id er ikke sat i Redirect->reset", E_USER_ERROR);
        }

        // Vi sletter de
        $result = $this->db->query("SELECT id FROM redirect
            WHERE
                (session_id = ".$this->db->quote($this->session_id, 'text')." AND
                    ".$this->extra_db_condition['select']."
                    id != ".$this->db->quote($this->id, 'integer')." AND
                    destination_url = ".$this->db->quote($this->thisUri(), 'text').")
                OR (".$this->extra_db_condition['select']."
                    date_created < DATE_SUB(NOW(), INTERVAL 24 HOUR))");

        if (PEAR::isError($result)) {
            trigger_error('Error in query: '.$result->getUserInfo(), E_USER_ERROR);
            return false;
        }
        while($row = $result->fetchRow(MDB2_FETCHMODE_ASSOC)) {
            $this->delete($row['id']);
        }

        return true;
    }

    /**
     * Delete a single redirect
     *
     * @param integer $id Id of redirect or if not set the current redirect.
     *
     * @return boolean true on success
     */
    public function delete($id = NULL)
    {
        if ($id === NULL) {
            $id = $this->id;
        }
        if ($id == 0) {
            return true;
        }
        $result = $this->db->exec("DELETE FROM redirect_parameter_value WHERE ".$this->extra_db_condition['select']." redirect_id = ".intval($id));
        if (PEAR::isError($result)) {
            trigger_error('Error in exec: '.$result->getUserInfo(), E_USER_ERROR);
            return false;
        }
        $result = $this->db->exec("DELETE FROM redirect_parameter WHERE ".$this->extra_db_condition['select']." redirect_id = ".intval($id));
        if (PEAR::isError($result)) {
            trigger_error('Error in exec: '.$result->getUserInfo(), E_USER_ERROR);
            return false;
        }
        $result = $this->db->exec("DELETE FROM redirect WHERE ".$this->extra_db_condition['select']." id = ".intval($id));
        if (PEAR::isError($result)) {
            trigger_error('Error in exec: '.$result->getUserInfo(), E_USER_ERROR);
            return false;
        }
        return true;
    }

    /**
     * Used to set a parameter - if more parameters should be set
     *
     * @todo WHY IS THIS METHOD CALLED ASK?
     *
     * @param string $key  Identifier of the parameter
     * @param type   $type Can be either mulitple or single
     *
     * @return boolean
     */
    public function askParameter($key, $type = 'single')
    {
        if ($this->id == 0) {
            trigger_error("You need to use setDestination() before you use askParameter()", E_USER_ERROR);
            return false;
        }

        $multiple = 0;
        if (!in_array($type, array('single', 'multiple'))) {
            trigger_error('Invalid type "'.$type.'" in Redirect->askParameter. It can either be "single" or "multiple"', E_USER_ERROR);
        }
        if ($type == 'multiple') {
            $multiple = 1;
        }

        $result = $this->db->exec("INSERT INTO redirect_parameter SET ".$this->extra_db_condition['insert']." redirect_id = ".$this->db->quote($this->id, 'integer').", parameter = ".$this->db->quote($key, 'text').", multiple = ".$this->db->quote($multiple, 'integer')."");
        if (PEAR::isError($result)) {
            trigger_error('Error in exec: '.$result->getUserInfo(), E_USER_ERROR);
            return false;
        }
        return true;
    }

    /**
     * Sets a parameter - both single and multiple - must be called right before location
     *
     * SHOW AN EXAMPLE
     *
     * @return boolean
     */
    public function setParameter($key, $value, $extra_value = '')
    {
        if ($this->id == 0) {
            trigger_error("id is not set IN Redirect->setParameter. You might want to consider the possibility that redirect id both could and could not be set by the call of setParameter, and therefor want to make a check before.", E_USER_ERROR);
        }

        $result = $this->db->query("SELECT id, multiple FROM redirect_parameter WHERE ".$this->extra_db_condition['select']." redirect_id = ".$this->db->quote($this->id, 'integer')." AND parameter = ".$this->db->quote($key, 'text')."");
        if (PEAR::isError($result)) {
            trigger_error('Error in exec: '.$result->getUserInfo(), E_USER_ERROR);
            return false;
        }
        if ($row = $result->fetchRow(MDB2_FETCHMODE_ASSOC)) {
            $parameter_id = $row['id'];

            if ($row['multiple'] == 1) {
                $result = $this->db->query("INSERT INTO redirect_parameter_value SET ".$this->extra_db_condition['insert']." redirect_id = ".$this->db->quote($this->id, 'integer').", redirect_parameter_id = ".$this->db->quote($row['id'], 'integer').", value = ".$this->db->quote($value, 'text').", extra_value = ".$this->db->quote($extra_value, 'text')."");
                if (PEAR::isError($result)) {
                    trigger_error('Error in exec: '.$result->getUserInfo(), E_USER_ERROR);
                    return false;
                }
                return true;
            } else {

                $result = $this->db->query("SELECT id FROM redirect_parameter_value WHERE ".$this->extra_db_condition['select']." redirect_id = ".$this->db->quote($this->id, 'integer')." AND redirect_parameter_id = ".$this->db->quote($row['id'], 'integer'));
                if (PEAR::isError($result)) {
                    trigger_error('Error in query: '.$result->getUserInfo(), E_USER_ERROR);
                    return false;
                }
                if ($row = $result->fetchRow(MDB2_FETCHMODE_ASSOC)) {
                    $result = $this->db->exec("UPDATE redirect_parameter_value SET value = ".$this->db->quote($value, 'text').", extra_value = ".$this->db->quote($extra_value, 'text')." WHERE ".$this->extra_db_condition['select']." redirect_id = ".$this->db->quote($this->id, 'integer')." AND  redirect_parameter_id = ".$this->db->quote($parameter_id, 'integer'));
                    if (PEAR::isError($result)) {
                        trigger_error('Error in exec: '.$result->getUserInfo(), E_USER_ERROR);
                        return false;
                    }
                } else {
                    $result = $this->db->exec("INSERT INTO redirect_parameter_value SET ".$this->extra_db_condition['insert']." redirect_id = ".$this->db->quote($this->id, 'integer').", redirect_parameter_id = ".$this->db->quote($parameter_id, 'integer').", value = ".$this->db->quote($value, 'text').", extra_value = ".$this->db->quote($extra_value, 'text')."");
                    if (PEAR::isError($result)) {
                        trigger_error('Error in exec: '.$result->getUserInfo(), E_USER_ERROR);
                        return false;
                    }
                }
                return true;
            }
        } else {
            return false;
        }
    }

    /**
     * Tells whether the request is a multiple value
     *
     * @param string $key The identifer of the parameter
     *
     * @return boolean
     */
    public function isMultipleParameter($key)
    {
        if ($this->id == 0) {
            trigger_error("id er ikke sat i Redirect->isMultipleParameter", E_USER_ERROR);
        }
        $result = $this->db->query("SELECT id FROM redirect_parameter WHERE ".$this->extra_db_condition['select']." redirect_id = ".$this->db->quote($this->id, 'integer')." AND parameter = ".$this->db->quote($key, 'text')." AND multiple = 1");
        if (PEAR::isError($result)) {
            trigger_error('Error in query: '.$result->getUserInfo(), E_USER_ERROR);
            return false;
        }
        return $result->numRows() > 0;
    }

    /**
     * Removes a parameter
     *
     * @param string $key   The key of the value to remove
     * @param array  $value The value to remove
     *
     * @return mixed
     */
    public function removeParameter($key, $value)
    {
        if ($this->id == 0) {
            trigger_error("id er ikke sat i Redirect->removeParameter", E_USER_ERROR);
        }

        $result = $this->db->query("SELECT id FROM redirect_parameter WHERE ".$this->extra_db_condition['select']." redirect_id = ".$this->db->quote($this->id, 'integer')." AND parameter = ".$this->db->quote($key, 'text')."");
        if (PEAR::isError($result)) {
            trigger_error('Error in exec: '.$result->getUserInfo(), E_USER_ERROR);
            return false;
        }
        if ($row = $result->fetchRow(MDB2_FETCHMODE_ASSOC)) {
            $result = $this->db->exec("DELETE FROM redirect_parameter_value WHERE ".$this->extra_db_condition['select']." redirect_id = ".$this->db->quote($this->id, 'integer')." AND redirect_parameter_id = ".$this->db->quote($row['id'], 'integer')." AND value = ".$this->db->quote($value, 'text')."");
            if (PEAR::isError($result)) {
                trigger_error('Error in exec: '.$result->getUserInfo(), E_USER_ERROR);
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Gets multiple parameter
     *
     * @param string $key              Gets the following parameter
     * @param array  $with_extra_value @todo WHAT IS THIS
     *
     * @return mixed
     */
    public function getParameter($key, $with_extra_value = '')
    {
        if ($this->id == 0) {
            trigger_error('id er ikke sat i Redirect->getParameter', E_USER_ERROR);
        }

        $i = 0;
        $parameter = array();
        $multiple = 0;
        $result = $this->db->query('SELECT id, multiple FROM redirect_parameter WHERE '.$this->extra_db_condition['select'].' redirect_id = '.$this->db->quote($this->id, 'integer').' AND parameter = '.$this->db->quote($key, 'text').'');
        if (PEAR::isError($result)) {
            trigger_error('Error in exec: '.$result->getUserInfo(), E_USER_ERROR);
            return false;
        }
        if ($row = $result->fetchRow(MDB2_FETCHMODE_ASSOC)) {
            $multiple = $row['multiple'];
            $result = $this->db->query('SELECT id, value, extra_value FROM redirect_parameter_value WHERE '.$this->extra_db_condition['select'].' redirect_parameter_id = '.$this->db->quote($row['id'], 'integer').' ORDER BY id');
            if (PEAR::isError($result)) {
                trigger_error('Error in exec: '.$result->getUserInfo(), E_USER_ERROR);
                return false;
            }
            while($row = $result->fetchRow(MDB2_FETCHMODE_ASSOC)) {
                if ($with_extra_value == 'with_extra_value') {

                    $parameter[$i]['value'] = $row['value'];
                    $parameter[$i]['extra_value'] = $row['extra_value'];
                } else {
                    $parameter[$i] = $row['value'];
                }
                $i++;
            }
        }

        if ($multiple == 1) {
            return $parameter;
        } else {
            if (array_key_exists(0, $parameter)) {
                return $parameter[0];
            } else {
                return '';
            }
        }
    }

    /**
     * Returns the identifier
     *
     * @todo replace all instances of calls to $redirect->get('identifier');
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->get('identifier');
    }

    /**
     * Returns the id
     *
     * @todo replace all instances of calls to $redirect->get('id');
     *
     * @return integer
     */
    public function getId()
    {
        return $this->get('id');
    }

    /**
     * Returns the redirect query string
     *
     * @todo replace all instances of calls to $redirect->get('redirect_query_string');
     *
     * @return string
     */
    public function getRedirectQueryString()
    {
        return $this->get('redirect_query_string');
    }

    /**
     * Returns the redirect query string
     *
     * @todo replace all instances of calls to $redirect->get('return_url') with this one;
     *
     * @return string
     */
    public function getReturnUrl()
    {
        return $this->get('return_url');
    }

    /**
     *
     * @todo this should be private soon
     */
    public function get($key)
    {
        if (isset($this->value[$key])) {
            return $this->value[$key];
        } else {
            return '';
        }
    }

    /**
     * Checks whether the uri is valid
     *
     * @param string $url Url to check
     *
     * @return boolean
     */
    private function isUrlValid($url)
    {
        return !(substr($url, 0, 7) != 'http://' && substr($url, 0, 8) != 'https://');
    }

    /**
     * Returns an url to go to if cancelling redirect flow
     *
     * @param string $default Gets the url to redirect to
     *
     * @return string
     */
    public function getCancelUrl($default)
    {
        if ($this->id > 0) {
            return $this->get('cancel_url');
        } else {
            return $default;
        }
    }
}
