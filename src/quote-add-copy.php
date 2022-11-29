<?php

      $file_get = file_get_contents('https://drive.google.com/uc?id=11OFm-jZ0uSAZ3sthSI_hfFyVChgjdWPN&export=download');
      $extension = pathinfo(parse_url('https://drive.google.com/uc?id=11OFm-jZ0uSAZ3sthSI_hfFyVChgjdWPN&export=download', PHP_URL_PATH), PATHINFO_EXTENSION);
      echo $extension;
      file_put_contents('images/gugug.jpg',$file_get);