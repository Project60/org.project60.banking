DESTDIR = org.project60.banking
ZIPNAME = civibanking

VERSION := $(shell head -1 NEWS)
DATE := $(shell date '+%Y-%m-%d')

dist:
	sed -i 's/<version>[^<]\+/<version>$(VERSION)/;s/<releaseDate>[^<]\+/<releaseDate>$(DATE)/' info.xml
	rm -rf build/$(DESTDIR)
	mkdir -p build/$(DESTDIR)
	git ls-files|xargs cp -l -t build/$(DESTDIR) --parents
	cd build && rm -f $(ZIPNAME)-$(VERSION).zip && zip -r $(ZIPNAME)-$(VERSION).zip $(DESTDIR)
