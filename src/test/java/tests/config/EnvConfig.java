package tests.config;

/*

    *
 */
public class EnvConfig {

    // Base URLs
    public static final String FRONTEND_URL = "http://18.203.69.17";
    public static final String BACKEND_URL = "http://34.244.13.53";
    //public static final String BACKEND_API_URL = BACKEND_URL + "/api";
    public static final String FUSEKI_URL = "http://localhost:3030";
    public static final String YASGUI_ENDPOINT = "http://localhost:8888";
    //Path URL
    public static final String LOGIN_URL = FRONTEND_URL + "/user/login";
    public static final String NAMESPACES_URL = FRONTEND_URL + "/rep/manage/namespaces";
    public static final String FILES_URL = FRONTEND_URL + "/rep/select/mt/";
    public static final String UPLOAD_URL = FRONTEND_URL + "/rep/manage/addmt/";
    // Credentials
    public static final String USERNAME = "admin";
    public static final String PASSWORD = "admin";

    // Timeout settings
    public static final int DEFAULT_WAIT_SECONDS = 15;
    public static final int MAX_ATTEMPTS = 30;
    public static final int WAIT_BETWEEN_ATTEMPTS_MS = 10000;


    private EnvConfig() {
        // Private constructor to prevent instantiation
    }
}
