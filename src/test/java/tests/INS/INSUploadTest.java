package tests.INS;

import java.io.File;

import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.Test;

import tests.base.BaseUpload;
public class INSUploadTest extends BaseUpload {

    private final String insType = System.getProperty("insType", "nhanes");

    @Test
    @DisplayName("Upload a valid INS file with basic data")
    void shouldUploadINSFileSuccessfully() throws InterruptedException {
        navigateToUploadPage("ins");
        switch (insType) {
            case "nhanes":
                fillInputByLabel("Name", "testeINS");
                fillInputByLabel("Version", "1");

                File file = new File("src/test/java/tests/testfiles/INS-NEW-PHQ9.xlsx");
                uploadFile(file);

                submitFormAndVerifySuccess();
                Thread.sleep(5000);
                /*
                navigateToUploadPage("ins");

                fillInputByLabel("Name", "testeINSHIERARCHY");
                fillInputByLabel("Version", "1");

                File filehi = new File("tests/testfiles/INS-NHANES-2017-2018-HIERARCHY.xlsx");
                uploadFile(filehi);
                submitFormAndVerifySuccess();

                 */
            case "WS":
                fillInputByLabel("Name", "testeINS");
                fillInputByLabel("Version", "1");

                File fileWS = new File("src/test/java/tests/testfiles/INS-LTE-PIAGET-WEATHER-STATION.xlsx");
                uploadFile(fileWS);

                submitFormAndVerifySuccess();
                break;
    }

    }
}
