package tests.DSG;

import java.io.File;

import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.Test;

import tests.base.BaseUpload;
public class DSGUploadTest extends BaseUpload {

    private final String dsgType = System.getProperty("dsgType", "WS");
    @Test
    @DisplayName("Upload a valid DSG file with basic data")
    void shouldUploadDSGFileSuccessfully() {
        navigateToUploadPage("dsg");

        fillInputByLabel("Name", "testeDSG");
        fillInputByLabel("Version", "1");

        switch(dsgType) {
            case "nhanes":
                File file = new File("src/test/java/tests/testfiles/DSG-NHANES-2017-2018_13.xlsx");
                uploadFile(file);
                submitFormAndVerifySuccess();
                break;
            case "WS":
                File fileWS = new File("src/test/java/tests/testfiles/DSG-LTE-PIAGET-WEATHER-STATION.xlsx");
                uploadFile(fileWS);
                submitFormAndVerifySuccess();
                break;
            default:
                throw new IllegalArgumentException("Invalid DSG type: " + dsgType);
        }
    }
}
