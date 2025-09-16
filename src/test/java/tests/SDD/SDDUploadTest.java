package tests.SDD;

import java.io.File;

import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.Test;

import tests.base.BaseUpload;

public class SDDUploadTest extends BaseUpload {

    private final String sddType = System.getProperty("sddType", "DEMO"); // default to DPQ



    @Test
    @DisplayName("Upload a valid SDD file with type DPQ or DEMO")
      void shouldUploadSDDFileSuccessfully() throws InterruptedException {
        switch(sddType){
            case "nhanes" :
        System.out.println("Uploading SDD file with type NHANES: " + sddType);
        navigateToUploadPage("sdd");
        fillInputByLabel("Name", "testeSDDNHANES");
        fillInputByLabel("Version", "1");
        File filenhanes = new File("src/test/java/tests/testfiles/SDD-NHANES-2017-2018-" + sddType + ".xlsx");
        uploadFile(filenhanes);
        submitFormAndVerifySuccess();
        break;
        case "WS" :
            navigateToUploadPage("sdd");
            System.out.println("Uploading SDD file with type WS" + sddType);

            fillInputByLabel("Name", "testeSDDWS");
            fillInputByLabel("Version", "1");

            File filews = new File("src/test/java/tests/testfiles/SDD-WS.xlsx");;
            uploadFile(filews);

            submitFormAndVerifySuccess();
            break;
        default:
                throw new IllegalArgumentException("Invalid SDD type: " + sddType);
        }
    }

}
