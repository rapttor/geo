<?php // ﷽‎
namespace RapTToR;


/**
 * @author rapttor
 * require_once(__DIR__ . '/vendor/autoload.php'); 
 * classes/export: geo
 */

$RAPTOR_GEODATA = array();
defined("RAPTTOR_GETDATA_COUNTRIES") || define("RAPTTOR_GETDATA_COUNTRIES", "RAPTTOR_GETDATA_COUNTRIES");
defined("RAPTTOR_GETDATA_STATES") || define("RAPTTOR_GETDATA_STATES", "RAPTTOR_GETDATA_STATES");
defined("RAPTTOR_GETDATA_CITIES") || define("RAPTTOR_GETDATA_CITIES", "RAPTTOR_GETDATA_CITIES");
defined("RAPTTOR_GETDATA_CONTINENTS") || define("RAPTTOR_GETDATA_CONTINENTS", "RAPTTOR_GETDATA_CONTINENTS");
defined("RAPTTOR_GETDATA_LANGUAGES") || define("RAPTTOR_GETDATA_LANGUAGES", "RAPTTOR_GETDATA_LANGUAGES");

class Geo
{

    private $ipApiUrl = "http://ip-api.com/json/";
    public $data = array();

    public static function types()
    {
        return array(
            RAPTTOR_GETDATA_CITIES => array("filename" => "cities.json"),
            RAPTTOR_GETDATA_COUNTRIES => array("filename" => "countries.json"),
            RAPTTOR_GETDATA_STATES => array("filename" => "states.json"),
            RAPTTOR_GETDATA_CONTINENTS => array("filename" => "continents.json"),
            RAPTTOR_GETDATA_LANGUAGES => array("filename" => "languages.json"),
        );
    }



    public static function loadFile($type)
    {
        $types = self::types();
        $file = false;
        if (isset($types[$type]))
            $file = $types[$type]['filename'];
        $filename = __DIR__ . "/../json/" . $file;
        if (is_file($filename)) {
            $data = file_get_contents($filename);
            if (stripos($file, '.json') !== false)
                $data = json_decode($data, true);
            if (stripos($file, '.csv') !== false)
                $data = \RapTToR\Helper::csv2arr($data);
            return $data;
        }
        return false;
    }

    /**
     * Load the geodata based on the specified type.
     *
     * @param string $type The type of geodata to load. Default is "*".
     * @param bool $force Force the loading of geodata even if it already exists. Default is false.
     * @throws Exception If an error occurs while loading the geodata.
     */
    public static function load($type = "*", $force = false)
    {
        global $RAPTTOR_GEODATA;
        if (!is_array($RAPTTOR_GEODATA))
            $RAPTTOR_GEODATA = array();
        foreach (self::types() as $t)
            if ($type == $t || $type == "*")
                if (!isset($RAPTTOR_GEODATA[$type]) || $force)
                    $RAPTTOR_GEODATA[$type] = self::load($type);

        if ($type == "*")
            return $RAPTTOR_GEODATA;
        return $RAPTTOR_GEODATA[$type];
    }

    public static function get($type = RAPTTOR_GETDATA_COUNTRIES, $force = false)
    {
        return self::load($type, $force);
    }

    public function countries($filter = array())
    {
        $data = self::get(RAPTTOR_GETDATA_COUNTRIES);
        return $data;
    }

    /**
     * Retrieves a list of states for a given country.
     *
     * @param string $country (optional) The country code. Defaults to "US".
     * @return array An array of states for the given country.
     */
    public function states($country = "US")
    {
        $country = strtolower($country);
        $result = array();
        $data = self::get(RAPTTOR_GETDATA_STATES);
        foreach ($data as $k => $v)
            if (strtolower($v) == $country)
                $result[$k] = $v;
        return $result;
    }
    public function cities($country = "US")
    {
        $data = self::get(RAPTTOR_GETDATA_CITIES);
        return $data;
    }

    /**
     * Determine the user's country of origin as a 2-letter ISO code.
     * Usage example
     * $detector = new \RapTToR\Geo();
     * $countryCode = $detector->getCountryCode();
     * echo $countryCode ? "User's country: $countryCode" : "Unable to determine user's country.";
     *
     * @return string|null The country code (e.g., 'US', 'GB'), or null if not determinable.
     */
    public function getCountryCode(): ?string
    {
        // Attempt to determine country from browser language
        $countryCode = $this->getCountryFromBrowserLanguage();
        if ($countryCode) {
            return $countryCode;
        }

        // Attempt to determine country from IP address
        $ipAddress = $this->getUserIpAddress();
        if ($ipAddress) {
            $countryCode = $this->getCountryFromIp($ipAddress);
            if ($countryCode) {
                return $countryCode;
            }
        }

        return null; // Country could not be determined
    }

    /**
     * Get the country code from browser language configuration.
     *
     * @return string|null The country code, or null if not available.
     */
    private function getCountryFromBrowserLanguage(): ?string
    {
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($languages as $language) {
                $locale = explode(';', $language)[0];
                $countryCode = $this->extractCountryFromLocale($locale);
                if ($countryCode) {
                    return strtoupper($countryCode);
                }
            }
        }

        return null;
    }

    /**
     * Extract the country code from a locale string.
     *
     * @param string $locale The locale string (e.g., 'en-US', 'fr-CA').
     * @return string|null The country code, or null if not extractable.
     */
    private function extractCountryFromLocale(string $locale): ?string
    {
        if (strpos($locale, '-') !== false) {
            $parts = explode('-', $locale);
            return $parts[1] ?? null;
        }

        return null;
    }

    /**
     * Get the user's IP address.
     *
     * @return string|null The IP address, or null if not determinable.
     */
    private function getUserIpAddress(): ?string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return null;
    }

    /**
     * Get the country code from an IP address using a geolocation API.
     *
     * @param string $ipAddress The IP address to geolocate.
     * @return string|null The country code, or null if not determinable.
     */
    private function getCountryFromIp(string $ipAddress): ?string
    {
        $response = @file_get_contents($this->ipApiUrl . $ipAddress);
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['countryCode'])) {
                return $data['countryCode'];
            }
        }

        return null;
    }


    /**
     * Retrieve geolocation data for a given IP address from a remote API.
     *
     * @param string $ipAddress The IP address to geolocate.
     * @return array|null The geolocation data, or null if not determinable.
     */
    private function getIpData($ipAddress = false): 
    {
        if (empty($ipAddress) || !$ipAddress)
            $ipAddress = $this->getUserIpAddress();
        $response = @file_get_contents($this->ipApiUrl . $ipAddress);
        if ($response) {
            $data = json_decode($response, true);
            return $data;
        }

        return null;
    }

    /**
     * Block users from specified countries based on options.
     *
     * @param array $options Custom options to override defaults.
     * @param array $default Default configuration: ['arrayIso2Codes' => [], 'redirect' => 'block'].
     */
    public function blockCountries(array $options, array $default = ["arrayIso2Codes" => [], "redirect" => "block"]): void
    {
        $config = array_merge($default, $options);
        $countryCode = $this->getCountryCode();

        if (in_array($countryCode, $config['arrayIso2Codes'], true)) {
            if ($config['redirect'] === 'block') {
                header("HTTP/1.1 403 Forbidden");
                echo "Access to this site is restricted for users from your location.";
                exit;
            } else {
                header("Location: {$config['redirect']}");
                exit;
            }
        }
    }

}