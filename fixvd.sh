#!/bin/bash 

FILE=$1

# Get the release version number
VERSION=$2;
if [ -z "VERSION" ] 
then
  echo "Must specify version number by setting version with first parameter"
fi

# Get the release date
DATE="$(date "+%B %e, %Y")"

echo "fixvd $1 with version :$VERSION date:$DATE";

sed -i 's%\(<version>\)[^<]*\(<\/version>\)%\1'$VERSION'\2%' $FILE
sed -i 's%^\(<creationDate>\)[^<]*\(</creationDate>\)%\1'"$DATE"'\2%' $FILE
sed -i 's%^\*\*Version .+ - [^\*]+[0-9]+\*\*$%**Version '$VERSION' - '"$DATE"'**%' $FILE
sed -i 's%\([v\-]*\)[0-9]\.[0-9]\.[0-9]%\1'$VERSION'%g' $FILE

