git subsplit init git@github.com:univeros/framework.git
git subsplit publish --heads="master" --no-tags src/Altair/Structure:git@github.com:univeros/base.git
git subsplit publish --heads="master" --no-tags src/Altair/Structure:git@github.com:univeros/configuration.git
git subsplit publish --heads="master" --no-tags src/Altair/Container:git@github.com:univeros/container.git
git subsplit publish --heads="master" --no-tags src/Altair/Container:git@github.com:univeros/cookie.git
git subsplit publish --heads="master" --no-tags src/Altair/Container:git@github.com:univeros/filesystem.git
git subsplit publish --heads="master" --no-tags src/Altair/Container:git@github.com:univeros/http.git
git subsplit publish --heads="master" --no-tags src/Altair/Container:git@github.com:univeros/queue.git
git subsplit publish --heads="master" --no-tags src/Altair/Container:git@github.com:univeros/security.git
git subsplit publish --heads="master" --no-tags src/Altair/Structure:git@github.com:univeros/structure.git
rm -rf .subsplit/
