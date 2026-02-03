VERSION = "1.0.2"
PACKAGE = plg_content_jowebpreview
ZIPFILE = $(PACKAGE)-$(VERSION).zip
UPDATEFILE = $(PACKAGE)-update.xml
ROOT = $(shell pwd)


.PHONY: $(ZIPFILE)

ALL : $(ZIPFILE) fixsha

ZIPIGNORES = -x "fix*.*" -x "Makefile" -x "*.git*" -x "*.svn*" -x "thumbs/*" -x "*.zip" -x "lib/simplehtmldom/tests/*" -x "lib/simplehtmldom/docs/*" -x "lib/simplehtmldom/example/*" -x "lib/simplehtmldom/.mkdocs/*" -x "lib/simplehtmldom/composer.*"

$(ZIPFILE): 
	@echo "-------------------------------------------------------"
	@echo "Creating zip file for: $(PACKAGE)"
	@rm -f $@
	@(cd $(ROOT); zip -r $@ * $(ZIPIGNORES))

fixversions:
	@echo "Updating all install xml files to version $(VERSION)"
	@find . \( -name '*.xml' ! -name 'default.xml' ! -name 'metadata.xml' ! -name 'config.xml' \) -exec  ./fixvd.sh {} $(VERSION) \;

revertversions:
	@echo "Reverting all install xml files"
	@find . \( -name '*.xml' ! -name 'default.xml' ! -name 'metadata.xml' ! -name 'config.xml' \) -exec git checkout {} \;

fixsha:
	@echo "Updating update xml files with checksums"
	@(cd $(ROOT);./fixsha.sh $(ZIPFILE) $(UPDATEFILE))





