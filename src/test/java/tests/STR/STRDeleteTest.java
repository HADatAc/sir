package tests.STR;

import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.Test;
import tests.base.BaseDelete;

public class STRDeleteTest extends BaseDelete {

    @Test
    @DisplayName("Delete STR file by name")
    void shouldDeleteSTRByName() throws InterruptedException {
        // Uningest the file first if it exists
        deleteFile("str", "testeSTR");
        quit();
    }

}
