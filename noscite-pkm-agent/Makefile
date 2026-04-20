.PHONY: install run test lint format deploy

VENV_PYTHON = venv/bin/python

install:
	python3 -m venv venv && venv/bin/pip install -r requirements.txt

run:
	$(VENV_PYTHON) watcher.py

test:
	$(VENV_PYTHON) -m pytest tests/ -v

lint:
	$(VENV_PYTHON) -m ruff check .

format:
	$(VENV_PYTHON) -m black .

deploy:
	bash deploy_pkm.sh
