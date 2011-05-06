TAR_DIST_NAME=dynacase-control
TAR_DIST_DIR=dynacase-control-$(VERSION)-$(RELEASE)

TAR_DIST_OPTS=--owner 0 --group 0

VERSION=$(shell head -1 VERSION)
RELEASE=$(shell head -1 RELEASE)

OBJECTS=

all:
	@echo ""
	@echo "  Available targets:"
	@echo ""
	@echo "    tarball"
	@echo "    clean"
	@echo ""

tarball:
	mkdir -p tmp/$(TAR_DIST_DIR)
	tar -cf - \
		--exclude Makefile \
		--exclude tmp \
		--exclude test \
		--exclude mk.sh \
		--exclude $(TAR_DIST_NAME)-*-*.tar.gz \
		--exclude $(TAR_DIST_NAME)-*-*.autoinstall.php \
		--exclude "*~" \
		--exclude .git \
		. | tar -C tmp/$(TAR_DIST_DIR) -xf -
	tar -C tmp -zcf $(TAR_DIST_NAME)-$(VERSION)-$(RELEASE).tar.gz $(TAR_DIST_OPTS) $(TAR_DIST_DIR)
	rm -Rf tmp

autoinstall: tarball
	# cat $(TAR_DIST_NAME)-$(VERSION)-$(RELEASE).tar.gz | php -r 'echo"<?php\n";$$fh=fopen("php://stdin","r");$$content=stream_get_contents($$fh);fclose($$fh);print"\$$content = <<<EOF\n".base64_encode($$content)."\nEOF;\n\$$proc=popen(\"tar zxf -\",\"w\");\nfwrite(\$$proc,base64_decode(\$$content));\nfclose(\$$proc);\nheader(\"Location: Dynacase-Control-$(VERSION)-$(RELEASE)\");\n?>";' > $(TAR_DIST_NAME)-$(VERSION)-$(RELEASE).autoinstall.php
	cat $(TAR_DIST_NAME)-$(VERSION)-$(RELEASE).tar.gz | php -r 'echo"<?php\n\$$s=filesize(__FILE__);\$$m=ini_get(\"memory_limit\");if(preg_match(\"/^(\d+)([kmg])$$/i\",\$$m,\$$r)){switch(strtolower(\$$r[2])){case\"g\":\$$r[1]*=1024;case\"m\":\$$r[1]*=1024;case\"k\":\$$r[1]*=1024;}\$$m=\$$r[1];}error_log(\"filesize=\".\$$s.\"/memory_limit=\".\$$m);if(\$$m<\$$s*4){echo\"You might need to set memory_limit >= \".\$$s*4;echo(0);}\n";$$fh=fopen("php://stdin","r");$$content=stream_get_contents($$fh);fclose($$fh);print"\$$content = <<<EOF\n".base64_encode($$content)."\nEOF;\n\$$proc=popen(\"tar zxf -\",\"w\");\nfwrite(\$$proc,base64_decode(\$$content));\nfclose(\$$proc);\nerror_log(\"memory_peak_usage=\".memory_get_peak_usage(true));\nheader(\"Location: Dynacase-Control-$(VERSION)-$(RELEASE)\");\n?>";' > $(TAR_DIST_NAME)-$(VERSION)-$(RELEASE).autoinstall.php


clean:
	find . -name "*~" -exec rm -f {} \;
	rm -Rf tmp
	rm -f $(TAR_DIST_NAME)-*.tar.gz
	rm -f $(TAR_DIST_NAME)-*.autoinstall.php
