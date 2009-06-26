TAR_DIST_NAME=freedom-wiff
TAR_DIST_DIR=wiff-$(VERSION)-$(RELEASE)

TAR_DIST_OPTS=--owner 0 --group 0

VERSION=$(shell head -1 VERSION)
RELEASE=$(shell head -1 RELEASE)

OBJECTS=

all:
	@echo ""
	@echo "  Available targets:"
	@echo ""
	@echo "    dist"
	@echo "    clean"
	@echo ""

dist:
	mkdir -p tmp/$(TAR_DIST_DIR)
	tar -cf - \
		--exclude Makefile \
		--exclude tmp \
		--exclude test \
		--exclude mk.sh \
		--exclude $(TAR_DIST_NAME)-*-*.tar.gz \
		--exclude $(TAR_DIST_NAME)-*-*.autoinstall.php \
		--exclude "*~" \
		. | tar -C tmp/$(TAR_DIST_DIR) -xf -
	tar -C tmp -zcf $(TAR_DIST_NAME)-$(VERSION)-$(RELEASE).tar.gz $(TAR_DIST_OPTS) $(TAR_DIST_DIR)
	rm -Rf tmp

autoinstall: dist
	cat $(TAR_DIST_NAME)-$(VERSION)-$(RELEASE).tar.gz | php -r 'echo"<?php\n";$$fh=fopen("php://stdin","r");$$content=stream_get_contents($$fh);fclose($$fh);print"\$$content = <<<EOF\n".base64_encode($$content)."\nEOF;\n\$$proc=popen(\"tar zxf -\",\"w\");\nfwrite(\$$proc,base64_decode(\$$content));\nfclose(\$$proc);\nheader(\"Location: wiff-$(VERSION)-$(RELEASE)\");\n?>";' > $(TAR_DIST_NAME)-$(VERSION)-$(RELEASE).autoinstall.php

clean:
	find . -name "*~" -exec rm -f {} \;
	rm -Rf tmp
	rm -f $(TAR_DIST_NAME)-$(VERSION)-$(RELEASE).tar.gz
	rm -f $(TAR_DIST_NAME)-$(VERSION)-$(RELEASE).autoinstall.php
