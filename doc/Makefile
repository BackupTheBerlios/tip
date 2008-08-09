PHPDOC = phpdoc --filename $(doc_list) --title "TIP manual" --directory tutorials --defaultcategoryname "HTML" --defaultpackagename "TIP" --readmeinstallchangelog $(extra_list) --sourcecode off
FORMATS = html

srcdir = ..

source_find  := find $(srcdir) -wholename "$(srcdir)/pear" -prune -or -wholename "$(srcdir)/doc" -prune -or -name '*.php' -and -print0 | xargs -0
source_files := `$(source_find)`
source_list  := `$(source_find) | tr ' ' ','`
extra_files  :=
extra_list   := $(extra_files: =,)
doc_files    := $(source_files) $(extra_files)
doc_list     := $(source_list),$(extra_list)

all: $(FORMATS) mostlyclean

html: old-stats stats
	@if cmp old-stats stats > /dev/null; then \
	  echo Documentation is up to date; \
	else \
	  test -d $@ || mkdir $@; \
	  $(PHPDOC) -tb . --target $@ --output "SQL:default"; \
	fi

stats:
	@stat -c%n,%Z $(doc_files) | sort > $@

old-stats:
	@mv -f stats $@ 2> /dev/null || touch $@

mostlyclean:
	@rm -f old-stats

clean: mostlyclean
	@rm -fr tip.sql stats $(FORMATS)

install: all
	mysql -Dntd -uroot -p < tip.sql

.PHONY:	old-stats stats html mostlyclean clean
