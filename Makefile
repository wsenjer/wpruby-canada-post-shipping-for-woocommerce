SLUG := wpruby-canada-post-shipping-for-woocommerce

.PHONY: test pack

test:
	vendor-bin/phpunit/vendor/bin/phpunit

# The release zip: the committed files, minus everything marked export-ignore in .gitattributes.
pack:
	-rm -f $(SLUG).zip
	git archive --prefix=$(SLUG)/ -o $(SLUG).zip HEAD
