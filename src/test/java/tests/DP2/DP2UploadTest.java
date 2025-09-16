package tests.DP2;

import java.io.File;

import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.Test;

import tests.base.BaseUpload;
public class DP2UploadTest extends BaseUpload {

    private final String dp2Type = System.getProperty("dp2Type", "WS");
    @Test
    @DisplayName("Upload a valid DP2 file with basic data")
    void shouldUploadDP2FileSuccessfully() {
        navigateToUploadPage("dp2");

        fillInputByLabel("Name", "testeDP2");
        fillInputByLabel("Version", "1");

        switch(dp2Type) {
            case "nhanes":
                File file = new File("src/test/java/tests/testfiles/DP2-NHANES-2017-2018.xlsx");
                System.out.println("Uploading file: " + file.getAbsolutePath());
                uploadFile(file);
                break;
            case "WS":
                File fileWS = new File("tests/testfiles/DP2-LTE-PIAGET-WEATHER-STATION.xlsx");
                System.out.println("Uploading file: " + fileWS.getAbsolutePath());
                uploadFile(fileWS);
                break;
            default:
                throw new IllegalArgumentException("Invalid DP2 type: " + dp2Type);
        }
    }
}
