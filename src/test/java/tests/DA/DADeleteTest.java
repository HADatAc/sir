package tests.DA;

import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.Test;
import org.openqa.selenium.By;
import tests.base.BaseDelete;

public class DADeleteTest extends BaseDelete {

    @Test
    @DisplayName("Delete DA file by name without uningest")
    void shouldDeleteDAByName() throws InterruptedException {
        // Uningest the file first if it exists
        deleteAllFiles("da");

        quit();
    }

}
