package tests.INS;

import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.Test;
import tests.base.BaseDelete;

public class INSDeleteTest extends BaseDelete {

    @Test
    @DisplayName("Delete all INS files")
    void shouldDeleteAllINSFiles() throws InterruptedException {
        deleteAllFiles("ins");
        quit();
    }
}
