<?php
      $test = '[{\"url\":\"https://drive.google.com/uc?id=1_3TKcpF6ykoeE_NCNvi-DkzLcIpBnxIJ&export=download\",\"name\":\"Unit Property Image QTE-2022-10-13\",\"extension\":\"jpg\"},{\"url\":\"https://drive.google.com/uc?id=1brQn8mpZ6OKNscB6mrX0pJkETA8xRLWa&export=download\",\"name\":\"Additional Unit Image QTE1-2022-10-13\",\"extension\":\"jpg\"},{\"url\":\"https://drive.google.com/uc?id=11OFm-jZ0uSAZ3sthSI_hfFyVChgjdWPN&export=download\",\"name\":\"Additional Unit Image QTE2-2022-10-13\",\"extension\":\"jpg\"}]';
      var_dump(json_decode(json_decode('"' . $test . '"',true)));