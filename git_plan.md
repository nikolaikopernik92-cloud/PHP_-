# План коммитов (PowerShell)

Задание требует не менее 8 содержательных коммитов. Проект уже собран целиком, поэтому добавляйте файлы порциями и коммитьте по шагам.
Лучше растянуть это на несколько дней и по ходу читать код каждого файла: на защите покажут историю коммитов и попросят объяснить код.

Один раз настройте Git (если ещё не настроен):
```
git config --global user.name "Оразбаев Махсуд"
git config --global user.email "ваш_email@example.com"
```

В папке проекта:
```
git init -b main

# 1
git add .gitignore var/data/.gitkeep docs/screenshots/.gitkeep
git commit -m "chore: структура проекта и .gitignore"

# 2
git add src/config.php src/storage.php
git commit -m "feat: JSON-хранилище с блокировкой и атомарной записью"

# 3
git add src/validation.php
git commit -m "feat: нормализация и серверная валидация формы операции"

# 4
git add src/transactions.php
git commit -m "feat: фильтры, итоги через array_filter/array_reduce и лимиты"

# 5
git add src/security.php src/view.php
git commit -m "feat: CSRF-токен, flash-сообщения и экранирование вывода"

# 6
git add src/controllers.php src/bootstrap.php public/index.php templates/layout.php templates/error.php templates/journal.php templates/add.php public/assets/style.css
git commit -m "feat: журнал операций и форма добавления с Post/Redirect/Get"

# 7
git add templates/summary.php templates/limits.php
git commit -m "feat: месячная сводка, остаток средств и лимиты категорий"

# 8
git add tests/run.php
git commit -m "test: автотесты валидации, расчётов, CSRF и хранилища"

# 9
git add scripts/seed.php
git commit -m "feat: скрипт демонстрационных данных"

# 10 (после того как сделаете скриншоты и положите их в docs/screenshots)
git add README.md test-cases.md docs/screenshots docs/presentation.pptx docs/presentation.pdf
git commit -m "docs: README, тестовые сценарии, скриншоты и презентация"
```

Проверьте перед отправкой: `git status` (пусто), `git log --oneline` (10 коммитов), в `var/data` нет ваших json-файлов в индексе.

Публикация:
```
# создайте пустой репозиторий на github.com, например php-expense-tracker, затем:
git remote add origin https://github.com/ВАШ_ЛОГИН/php-expense-tracker.git
git push -u origin main

# тег сдаваемой версии
git tag -a v1.0.0 -m "Сдаваемая версия: трекер расходов"
git push origin v1.0.0
```
После публикации впишите ссылку в README, презентацию (слайды 1 и 9) и сделайте ещё один коммит `docs: ссылка на репозиторий`.
