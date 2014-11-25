xgettext -s -c --no-wrap -d threewp_broadcast\ThreeWP_Broadcast -p lang -o "/home/edward/data/code/wordpress/plugins/threewp-broadcast/trunk/lang/ThreeWP_Broadcast.pot" \
    --omit-header -k_ -kerror_ -kdescription_ -kmessage_ -kheading_ -klabel_ -kname_ -koption_ -kp_ -ktext_ -kvalue_ \
    vendor/plainview/sdk/*php vendor/plainview/sdk/form2/inputs/*php vendor/plainview/sdk/form2/inputs/traits/*php vendor/plainview/sdk/wordpress/*php \
    include/*php \
    include/traits/*php

xgettext -s -c --no-wrap -d threewp_broadcast\blog_groups\ThreeWP_Broadcast_Blog_Groups -p lang -o "/home/edward/data/code/wordpress/plugins/threewp-broadcast/trunk/lang/ThreeWP_Broadcast_Blog_Groups.pot" \
    --omit-header -k_ -kerror_ -kdescription_ -kmessage_ -kheading_ -klabel_ -kname_ -koption_ -kp_ -ktext_ -kvalue_ ThreeWP_Broadcast_Blog_Groups.php vendor/plainview/sdk/*php vendor/plainview/sdk/form2/inputs/*php vendor/plainview/sdk/form2/inputs/traits/*php \
    include/blog_groups/*php \
    vendor/plainview/sdk/wordpress/*php
