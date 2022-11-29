<?php
      var_dump(json_decode('[{\"url\":\"https://drive.google.com/uc?id=1_3TKcpF6ykoeE_NCNvi-DkzLcIpBnxIJ&export=download\",\"name\":\"Unit Property Image QTE-2022-10-13\",\"extension\":\"jpg\"},{\"url\":\"https://drive.google.com/uc?id=1brQn8mpZ6OKNscB6mrX0pJkETA8xRLWa&export=download\",\"name\":\"Additional Unit Image QTE1-2022-10-13\",\"extension\":\"jpg\"},{\"url\":\"https://drive.google.com/uc?id=11OFm-jZ0uSAZ3sthSI_hfFyVChgjdWPN&export=download\",\"name\":\"Additional Unit Image QTE2-2022-10-13\",\"extension\":\"jpg\"}]'));
      $file_get = file_get_contents('https://drive.google.com/uc?id=11OFm-jZ0uSAZ3sthSI_hfFyVChgjdWPN&export=download');
      $extension = pathinfo(parse_url('https://drive.google.com/uc?id=11OFm-jZ0uSAZ3sthSI_hfFyVChgjdWPN&export=download', PHP_URL_PATH), PATHINFO_EXTENSION);
      echo $extension;
      file_put_contents('images/gugug.jpg',$file_get);