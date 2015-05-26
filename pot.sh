#!/bin/bash

# Create the POT files for Broadcast

# Collect a list of all PHP files
PHPFILES=""
# Find all PHP files
for file in `find ./ -type f -name "*php"` ; do
	PHPFILES="$PHPFILES $file"
done

POT="ThreeWP_Broadcast.pot"

xgettext -s -c --no-wrap -d threewp_broadcast\ThreeWP_Broadcast -p lang -o "$POT" \
	--omit-header -k_ -kerror_ -kdescription_ -kmessage_ -kheading_ -klabel_ -kname_ -koption_ -kp_ -ktext_ -kvalue_ \
	$PHPFILES
