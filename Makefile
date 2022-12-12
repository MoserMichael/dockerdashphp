PHPUNIT_FLAGS :=--stop-on-failure --colors="always" --no-coverage
PHPUNIT_FLAGS_DEBUG :=--colors="always" --no-coverage --printer="tests\blazemeter\PHPUnitPrinter\Printer"
SRC_DIR := src
TESTS_DIR := tests

test: ./${TESTS_DIR}
	./vendor/bin/phpunit ${PHPUNIT_FLAGS} --configuration ./${TESTS_DIR}/phpunit.xml $</$(patsubst $(TESTS_DIR)/%,%,$(TEST_FILE)) 2>&1 | tee test.log
	date

run:
	TRACE=2 ./run.sh 2>&1 | tee run.log

runphp:
	NUM_WORKERS=10 php -S "0.0.0.0:8001" -t src

runhttptest:
	php -S "0.0.0.0:8003"

update: composer.json
	composer update

install: update download-shells
	composer install

download-shells:
	./build/make-shells.sh

container-build:
	git clean -f -d
	docker build -f Dockerfile -t ghcr.io/mosermichael/phpdocker-mm:latest . 2>&1 | tee container-build.log

container-push:
	./build/container-push.sh ghcr.io/mosermichael/phpdocker-mm latest

