git subsplit init git@github.com:univeros/framework.git
git subsplit publish --heads="master" --no-tags src/Altair/Structure:git@github.com:univeros/structure.git
rm -rf .subsplit/
