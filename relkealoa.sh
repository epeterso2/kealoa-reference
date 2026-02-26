#! /bin/sh
#
# Release script for KEALOA Reference plugin
#
# @copyright 2026 Eric Peterson (eric@puzzlehead.org)
# @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
#

PLUGIN_ZIP=kealoa-reference.zip

rm -f ${PLUGIN_ZIP}
if [ -f ${PLUGIN_ZIP} ]
then
	echo Plugin file still exists: ${PLUGIN_ZIP}
	exit 1
else
	zip -r kealoa-reference kealoa-reference
fi

