#!/bin/bash

# Renames the Plainview SDK to something unique so that each plugin can use its own version.
# Uses sdk_rename.conf file to get the unique name (slug).

# Script must be run from plugin base directory (one dir up).

source scripts/sdk_rename.conf

if [ "$SLUG" == "" ]; then
	echo "sdk_rename.conf is incorrectly configured!"
	exit 1
fi

# Remove old SDK version
rm -rf vendor/plainview/sdk_$SLUG

cd vendor/plainview
mv sdk sdk_$SLUG
# Single backslash
perl -pi -e "s/plainview\\\\sdk/plainview\\\\sdk_$SLUG/" `find ./ -type f`
# Double backslash
perl -pi -e "s/plainview\\\\\\\\sdk/plainview\\\\\\\\sdk_$SLUG/" `find ./ -type f`
cd ../../

# Rename the SDK in the psr4 autoload
cd vendor/composer
perl -pi -e "s/sdk/sdk_$SLUG/g" `find ./ -type f`
cd ../../
