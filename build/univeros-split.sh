git subsplit init git@github.com:univeros/framework.git
git subsplit publish --heads="master" --no-tags src/Altair/Structure:git@github.com:univeros/common.git
git subsplit publish --heads="master" --no-tags src/Altair/Structure:git@github.com:univeros/configuration.git
git subsplit publish --heads="master" --no-tags src/Altair/Container:git@github.com:univeros/container.git
git subsplit publish --heads="master" --no-tags src/Altair/Courier:git@github.com:univeros/courier.git
git subsplit publish --heads="master" --no-tags src/Altair/Cookie:git@github.com:univeros/cookie.git
git subsplit publish --heads="master" --no-tags src/Altair/Filesystem:git@github.com:univeros/filesystem.git
git subsplit publish --heads="master" --no-tags src/Altair/Http:git@github.com:univeros/http.git
git subsplit publish --heads="master" --no-tags src/Altair/Middleware:git@github.com:univeros/middleware.git
git subsplit publish --heads="master" --no-tags src/Altair/Queue:git@github.com:univeros/queue.git
git subsplit publish --heads="master" --no-tags src/Altair/Security:git@github.com:univeros/security.git
git subsplit publish --heads="master" --no-tags src/Altair/Session:git@github.com:univeros/session.git
git subsplit publish --heads="master" --no-tags src/Altair/Structure:git@github.com:univeros/structure.git
git subsplit publish --heads="master" --no-tags src/Altair/Validation:git@github.com:univeros/validation.git
rm -rf .subsplit/
