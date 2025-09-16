package tests.SDD;

import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.Test;

import tests.base.BaseIngest;

public class SDDIngestNHANESTest extends BaseIngest {

    @Test
    @DisplayName("Ingest SDD file: testeSDDNHANES")
    void shouldingestsdd() throws InterruptedException {
        ingestSpecificSDD("testeSDDNHANES");
    }
}
