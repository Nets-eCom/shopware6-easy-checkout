# Misc
.DEFAULT_GOAL = help
.PHONY        : help

## —— Makefile   ——————————————————————————————————————————————————————————————
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9\./_ -]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

check-all: phpstan-check rector-check ecs-check phpunit
fix-all: ecs-fix rector-fix

phpstan-check: ## PHPStan analysis
	vendor/bin/phpstan

ecs-check: ## ECS analysis
	vendor/bin/ecs

rector-check: ## Rector analysis
	vendor/bin/rector process --dry-run

ecs-fix: ## ECS fix
	vendor/bin/ecs --fix

rector-fix: ## Rector Fix
	vendor/bin/rector process

phpunit: ## PHPUnit test
	vendor/bin/phpunit