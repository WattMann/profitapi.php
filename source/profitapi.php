<?php

namespace profitapi {
    use Exception;
    use profit_requests\request;

    class communicator
    {
        const API_VERSION = "1.4";
        const URL = "https://api.profit365.eu";

        private $auth_header;

        /**
         * Default constructor.
         * @param $auth_header auth_header
         */
        public function __construct($auth_header)
        {
            $this->auth_header = $auth_header;
        }

        /**
         * Used to communicate with api.
         * @param $request request Request to be sent timeout
         * @return integer response code
         */
        public function request($request)
        {
            if($request->getHeaderArray() == null)
                $headers = $this->auth_header->componentResult();
            else $headers = array_merge($this->auth_header->componentResult(), $request->getHeaderArray());

            $url = self::URL . "/" . self::API_VERSION . "/" . $request->getContext();
            $con = curl_init($url);

            if($request->getType() == request_type::POST_JSON) {
                $content = $request->getContent();
                array_push($headers, "Content-Type: application/json");
                curl_setopt($con, CURLOPT_POST, true);
                curl_setopt($con, CURLOPT_POSTFIELDS, $content);
            }

            curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($con, CURLOPT_HTTPHEADER, $headers);

            curl_exec($con);
            $code = curl_getinfo($con, CURLINFO_RESPONSE_CODE);
            curl_close($con);
            return $code;
        }
    }
    class auth_header extends request_component
    {
        const BASIC_AUTH_PATTERN = "/(.*.)(\@)(.*)(\...*):(..*)/";
        private $type;
        private $auth_key;
        private $client_secret;
        private $client_id;
        private $company_id;

        /**
         * auth_header constructor.
         * @param $type string
         * @param $auth_key string
         * @param $client_secret string
         * @param $client_guid string
         * @param null $company_id string
         * @throws Exception when authorization key doesnt match pattern when using BASIC auth
         */
        public function __construct($type, $auth_key, $client_secret, $client_guid, $company_id = null)
        {
            $this->type = $type;
            if ($type == auth_type::BASIC) {
                if (preg_match(self::BASIC_AUTH_PATTERN, $auth_key) != 1)
                    throw new Exception("basic authorization requires valid auth key");
                $this->auth_key = base64_encode($auth_key);
            } else
                $this->auth_key = $auth_key;

            $this->client_secret = $client_secret;
            $this->client_id = $client_guid;
            $this->company_id = $company_id;
        }

        function componentResult()
        {
            $headers = array(
                "ClientID: $this->client_id",
                "ClientSecret: $this->client_secret",
                "Authorization: $this->type $this->auth_key",
            );
            if ($this->company_id != null)
                array_push($headers, "CompanyID: $this->company_id");
            return $headers;
        }
    }
    abstract class request_component
    {
        abstract function componentResult();
    }
    class auth_type
    {
        const API_KEY = "apiKey";
        const BASIC = "basic";
    }
    class request_type {
        const POST_JSON = "postjson";
        const POST_XML = "postxml";
        const GET = "get";
    }

}

namespace profit_requests {

    use profit_data\invoice_data;
    use Exception;
    use profitapi\request_type;

    class sale_invoice_create_request extends request
    {
        /**
         * Default constructor
         * @param $invoice_data invoice_data
         * @throws Exception when required fields are missing
         */
        public function __construct($invoice_data)
        {
            // if (!$invoice_data->validate())
            //   throw new Exception("Invalid data supplied");
            parent::__construct(request_type::POST_JSON,null, json_encode($invoice_data->getData()));
        }

        function getContext()
        {
            return "sales/invoices";
        }
    }
    abstract class request
    {
        private $header_array;
        private $content;
        private $type;

        /**
         * request constructor.
         * @param $type string as declared in profitapi/request_type
         * @param $header_array array of strings that represent headers (optional)
         * @param $content string of contents (optional)
         */
        public function __construct($type, $header_array = null, $content = null)
        {
            $this->type = $type;
            $this->header_array = $header_array;
            $this->content = $content;
        }

        /**
         * @return string of the request type
         */
        public function getType()
        {
            return $this->type;
        }


        /**
         * @return array of strings representing header fields
         */
        public function getHeaderArray()
        {
            return $this->header_array;
        }

        /**
         * @return string represents HTTP content
         */
        public function getContent()
        {
            return $this->content;
        }

        abstract function getContext();
    }
}

namespace profit_data {

    use profit_requests\request;

    const GUID_REGEX_PATTERN = "\b[0-9a-f]{8}\b-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-\b[0-9a-f]{12}\b";

    function verifyGUID($guid) {
        return preg_match($guid, GUID_REGEX_PATTERN);
    }

    /* DOCUMENTATION: https://doc.profit365.eu/developers/en/api/doc/sales/invoices#section4-row */
    class invoice_row_data extends data {


        /**
         * invoice_row_data constructor.
         * @param $name string name of the item
         * @param $price double price of the item
         * @param $quantity integer quantity of the items
         */
        public function __construct($name, $price, $quantity = 1)
        {
            parent::__construct();
            $this->set("name", $name);
            $this->set("price", $price);
            $this->set("quantity", $quantity);
        }

    }
    /* DOCUMENTATION: https://doc.profit365.eu/developers/en/api/doc/sales/invoices#section4 */
    class invoice_data extends data {

        /**
         * invoice_data constructor.
         * @param $date string datetime formatted in ISO8601 format
         * @param $rows array of row data
         */
        public function __construct($date, $rows)
        {
            parent::__construct();
            $this->set("date", $date);
            $this->set("rows", $rows);
        }

    }

    abstract class data {
        protected $data;

        /**
         * data constructor.
         * @param $data
         */
        public function __construct($data = array()){
            $this->data = $data;
        }

        /**
         * @return mixed
         */
        public function getData()
        {
            return $this->data;
        }

        public function set($key, $val) {
            $this->$this->data[$key] = $val;
        }
    }

}