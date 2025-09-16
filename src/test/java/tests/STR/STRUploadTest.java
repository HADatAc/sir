package tests.STR;

import java.io.File;

import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.Test;

import tests.base.BaseUpload;
public class STRUploadTest extends BaseUpload {

    @Test
    @DisplayName("Upload a valid DSG file with basic data")
    void shouldUploadSTRFileSuccessfully() {
        navigateToUploadPage("str");

        fillInputByLabel("Name", "testeSTR");
        fillInputByLabel("Version", "1");

        File file = new File("src/test/java/tests/testfiles/STR-NHANES-2017-2018.xlsx");
        uploadFile(file);

        submitFormAndVerifySuccess();
    }
}
