package tests.DA;

import java.io.File;

import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.Test;

import tests.base.BaseUpload;

public class DAUploadTest extends BaseUpload {

    private final String daType = System.getProperty("daType", "DPQ"); // default to DPQ

    @Test
    @DisplayName("Upload a valid DA file with type DPQ or DEMO")
    void shouldUploadSDDFileSuccessfully() {


        navigateToUploadPage("da");

        fillInputByLabel("Name", "testeDA");
        fillInputByLabel("Version", "1");
        switch (daType) {
            case "DPQ":
                File filedemo = new File("src/test/java/tests/testfiles/DA-NHANES-2017-2018-" + daType + "_J.csv");
                uploadFile(filedemo);
                submitFormAndVerifySuccess();
                break;
            case "DEMO":
                File filedemo2 = new File("src/test/java/tests/testfiles/DA-NHANES-2017-2018-" + daType + "_J.csv");
                uploadFile(filedemo2);
                submitFormAndVerifySuccess();
                break;
            case "WS":
                File filews = new File("tests/testfiles/DA-NHANES-2017-2018-" + daType + "_J.csv");
                uploadFile(filews);
                submitFormAndVerifySuccess();

        }
    }
}
