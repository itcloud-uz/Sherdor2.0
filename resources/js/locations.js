const commonNeighborhoods = [
  "Markaz", "Do'stlik", "Tinchlik", "Navbahor", "Istiqlol", 
  "Yangi hayot", "Bahor", "Guliston", "Chorsu", "Mustaqillik", 
  "Vatan", "Yoshlik", "Ziyokor", "Lola", "Ma'rifat", "Navro'z", 
  "Obod", "Ozodlik", "Oqtepa", "Sohibkor", "Temiryo'lchi", 
  "Fayzli", "Humo", "Shodlik", "Yulduz"
];

function getNeighborhoods(specific = []) {
  return [...new Set([...specific, ...commonNeighborhoods])];
}

export const regionsData = {
  toshkent: {
    name: "Toshkent sh.",
    districts: {
      uchtepa: {
        name: "Uchtepa tumani",
        neighborhoods: getNeighborhoods(["Vatan", "Chilonzor 25-daha", "O'rikzor", "Ko'kcha Oqtepa", "Foziltepa", "Taqachi", "Farhod"])
      },
      chilonzor: {
        name: "Chilonzor tumani",
        neighborhoods: getNeighborhoods(["Gurlar", "Katta Chilonzor", "Qozirobod", "Tirsakobod", "Lutfiy", "Oqtepa", "Dilxush"])
      },
      yashnobod: {
        name: "Yashnobod tumani",
        neighborhoods: getNeighborhoods(["Tuzel-1", "Tuzel-2", "Do'stlik", "Asalobod", "Aviasozlar", "Beshariq", "Katta Qo'yliq"])
      },
      yakkasaroy: {
        name: "Yakkasaroy tumani",
        neighborhoods: getNeighborhoods(["Bobur", "Shota Rustaveli", "Konstitutsiya", "Rakat", "Boshliq", "Kushbegi", "Yunus Rajabiy"])
      },
      sergeli: {
        name: "Sergeli tumani",
        neighborhoods: getNeighborhoods(["Do'stlik", "Ittifoq", "Madadkor", "Sergeli-1", "Sergeli-5", "Sergeli-7", "Habibiy"])
      },
      yangihayot: {
        name: "Yangihayot tumani",
        neighborhoods: getNeighborhoods(["Choshtepa", "Yangi Choshtepa", "Fayzli", "Humo", "Navqiron", "Yo'ldosh"])
      },
      olmazor: {
        name: "Olmazor tumani",
        neighborhoods: getNeighborhoods(["Qoraqamish 1/2", "Qoraqamish 2/4", "Sebzor", "G'alaba", "Shifokorlar", "Paxta", "Universitet"])
      },
      mirzo_ulugbek: {
        name: "Mirzo Ulug'bek tumani",
        neighborhoods: getNeighborhoods(["Qorasuv 1", "Qorasuv 2", "Qorasuv 4", "Buyuk Ipak Yo'li", "Feruza", "Oliy Himmat", "Mustaqillik"])
      },
      mirobod: {
        name: "Mirobod tumani",
        neighborhoods: getNeighborhoods(["Fayzobod", "Mirobod", "Tong Yulduzi", "Zamin", "Oq uy", "Temiryo'lchilar", "Baynalmilal"])
      },
      shayxontohur: {
        name: "Shayxontohur tumani",
        neighborhoods: getNeighborhoods(["Ko'kcha", "Chorsu", "Xadra", "Jangoh", "Kamolon", "Labzak", "G'afur G'ulom"])
      },
      yunusobod: {
        name: "Yunusobod tumani",
        neighborhoods: getNeighborhoods(["Yunusobod 1-daha", "Yunusobod 4-daha", "Yunusobod 11-daha", "Yunusobod 19-daha", "Tiklanish", "Ming O'rik", "Bodomzor"])
      },
      bektemir: {
        name: "Bektemir tumani",
        neighborhoods: getNeighborhoods(["Suvsaz", "Binokor", "Abay", "Bektemir", "Husayn Boyqaro"])
      }
    }
  },
  samarqand: {
    name: "Samarqand",
    districts: {
      samarqand_sh: {
        name: "Samarqand shahri",
        neighborhoods: getNeighborhoods(["Bog'ishamol", "Sartepo", "Siyob", "Sozgari", "Qorasuv", "Temiryolchilar", "Geologlar", "Spitamen", "Vatanparvar", "Do'stlik", "Navro'z", "Ipak Yo'li", "Bog'i Baland", "Shohruh", "Al-Buxoriy"])
      },
      kattaqorgon_sh: {
        name: "Kattaqo'rg'on shahri",
        neighborhoods: getNeighborhoods(["Chinobod", "Go'zallik", "Do'stlik", "O'zbekiston", "G'alaba"])
      },
      bulungur: {
        name: "Bulung'ur tumani",
        neighborhoods: getNeighborhoods(["Bulung'ur", "Kildon", "Fozilmon", "Soxibkor", "Yulduz"])
      },
      ishtixon: {
        name: "Ishtixon tumani",
        neighborhoods: getNeighborhoods(["Ishtixon", "Mitan", "Azim", "Zarband", "Qoraqo'yli"])
      },
      jomboy: {
        name: "Jomboy tumani",
        neighborhoods: getNeighborhoods(["Jomboy shaharchasi", "Dehqonobod", "Nazar", "Qo'ng'irot", "Alisher Navoiy"])
      },
      kattaqorgon_t: {
        name: "Kattaqo'rg'on tumani",
        neighborhoods: getNeighborhoods(["Payshanba", "Kattaqo'rg'on", "Namuna", "Polvonlar"])
      },
      koshrabot: {
        name: "Qo'shrabot tumani",
        neighborhoods: getNeighborhoods(["Qo'shrabot", "Zarkent", "Pangat", "Jovus"])
      },
      narpay: {
        name: "Narpay tumani",
        neighborhoods: getNeighborhoods(["Oqtosh", "Qoraqo'zi", "Mirbozor", "Salovat"])
      },
      nurobod: {
        name: "Nurobod tumani",
        neighborhoods: getNeighborhoods(["Nurobod", "Sazag'on", "Jom", "Ulush"])
      },
      oqdaryo: {
        name: "Oqdaryo tumani",
        neighborhoods: getNeighborhoods(["Loyish", "Dahbed", "Oqbuloq", "Pishkan", "Yangiobod"])
      },
      paxtachi: {
        name: "Paxtachi tumani",
        neighborhoods: getNeighborhoods(["Ziyovuddin", "Qarnab", "Mingo'rik", "Misit"])
      },
      payariq: {
        name: "Payariq tumani",
        neighborhoods: getNeighborhoods(["Payariq", "Chelak", "Qo'shko'prik", "Tomoydi"])
      },
      pastdargom: {
        name: "Pastdarg'om tumani",
        neighborhoods: getNeighborhoods(["Juma", "Go'zalkent", "Charxin", "O'rta Charxin", "Nayman", "Sariqipchoq"])
      },
      samarqand_t: {
        name: "Samarqand tumani",
        neighborhoods: getNeighborhoods(["Qora suv", "Gulaqandoz", "Rovut", "Ohalik", "Mironqul", "Qo'shko'prik", "Rahmatobod"])
      },
      toyloq: {
        name: "Toyloq tumani",
        neighborhoods: getNeighborhoods(["Toyloq", "Qo'shchinor", "Bog'izag'on", "Adas", "Muborak"])
      },
      urgut: {
        name: "Urgut tumani",
        neighborhoods: getNeighborhoods(["Urgut shaharchasi", "G'ishtmasjid", "Pochvon", "Jartepa", "Sariqtepa", "Kamangaron"])
      }
    }
  },
  toshkent_v: {
    name: "Toshkent viloyati",
    districts: {
      angren_sh: { name: "Angren shahri", neighborhoods: getNeighborhoods() },
      olmaliq_sh: { name: "Olmaliq shahri", neighborhoods: getNeighborhoods() },
      ohangaron_sh: { name: "Ohangaron shahri", neighborhoods: getNeighborhoods() },
      bekobod_sh: { name: "Bekobod shahri", neighborhoods: getNeighborhoods() },
      chirchiq_sh: { name: "Chirchiq shahri", neighborhoods: getNeighborhoods() },
      yangiyol_sh: { name: "Yangiyo'l shahri", neighborhoods: getNeighborhoods() },
      bekabad_t: { name: "Bekobod tumani", neighborhoods: getNeighborhoods() },
      bostonliq_t: { name: "Bo'stonliq tumani", neighborhoods: getNeighborhoods() },
      boka_t: { name: "Bo'ka tumani", neighborhoods: getNeighborhoods() },
      chinoz_t: { name: "Chinoz tumani", neighborhoods: getNeighborhoods() },
      qibray_t: { name: "Qibray tumani", neighborhoods: getNeighborhoods() },
      ohangaron_t: { name: "Ohangaron tumani", neighborhoods: getNeighborhoods() },
      oqqorgon_t: { name: "Oqqo'rg'on tumani", neighborhoods: getNeighborhoods() },
      parkent_t: { name: "Parkent tumani", neighborhoods: getNeighborhoods() },
      piskent_t: { name: "Piskent tumani", neighborhoods: getNeighborhoods() },
      quyichirchiq_t: { name: "Quyi Chirchiq tumani", neighborhoods: getNeighborhoods() },
      zangiota_t: { name: "Zangiota tumani", neighborhoods: getNeighborhoods() },
      ortachirchiq_t: { name: "O'rta Chirchiq tumani", neighborhoods: getNeighborhoods() },
      yangiyol_t: { name: "Yangiyo'l tumani", neighborhoods: getNeighborhoods() },
      yuqorichirchiq_t: { name: "Yuqori Chirchiq tumani", neighborhoods: getNeighborhoods() },
      tashkent_t: { name: "Toshkent tumani", neighborhoods: getNeighborhoods() }
    }
  },
  fargona: {
    name: "Farg'ona",
    districts: {
      fargona_sh: { name: "Farg'ona shahri", neighborhoods: getNeighborhoods() },
      qoqon_sh: { name: "Qo'qon shahri", neighborhoods: getNeighborhoods() },
      margilon_sh: { name: "Marg'ilon shahri", neighborhoods: getNeighborhoods() },
      quvasoy_sh: { name: "Quvasoy shahri", neighborhoods: getNeighborhoods() },
      oltiariq_t: { name: "Oltiariq tumani", neighborhoods: getNeighborhoods() },
      bagdod_t: { name: "Bog'dod tumani", neighborhoods: getNeighborhoods() },
      beshariq_t: { name: "Beshariq tumani", neighborhoods: getNeighborhoods() },
      buvayda_t: { name: "Buvayda tumani", neighborhoods: getNeighborhoods() },
      dangara_t: { name: "Dang'ara tumani", neighborhoods: getNeighborhoods() },
      fargona_t: { name: "Farg'ona tumani", neighborhoods: getNeighborhoods() },
      furqat_t: { name: "Furqat tumani", neighborhoods: getNeighborhoods() },
      qoshtepa_t: { name: "Qo'shtepa tumani", neighborhoods: getNeighborhoods() },
      quva_t: { name: "Quva tumani", neighborhoods: getNeighborhoods() },
      rishton_t: { name: "Rishton tumani", neighborhoods: getNeighborhoods() },
      sox_t: { name: "So'x tumani", neighborhoods: getNeighborhoods() },
      toshloq_t: { name: "Toshloq tumani", neighborhoods: getNeighborhoods() },
      uchkoprik_t: { name: "Uchko'prik tumani", neighborhoods: getNeighborhoods() },
      uzbekiston_t: { name: "O'zbekiston tumani", neighborhoods: getNeighborhoods() },
      yozyovon_t: { name: "Yozyovon tumani", neighborhoods: getNeighborhoods() }
    }
  },
  andijon: {
    name: "Andijon",
    districts: {
      andijon_sh: { name: "Andijon shahri", neighborhoods: getNeighborhoods() },
      xonobod_sh: { name: "Xonobod shahri", neighborhoods: getNeighborhoods() },
      andijon_t: { name: "Andijon tumani", neighborhoods: getNeighborhoods() },
      asaka_t: { name: "Asaka tumani", neighborhoods: getNeighborhoods() },
      baliqchi_t: { name: "Baliqchi tumani", neighborhoods: getNeighborhoods() },
      boston_t: { name: "Bo'ston tumani", neighborhoods: getNeighborhoods() },
      buloqboshi_t: { name: "Buloqboshi tumani", neighborhoods: getNeighborhoods() },
      izboskan_t: { name: "Izboskan tumani", neighborhoods: getNeighborhoods() },
      jalaquduq_t: { name: "Jalaquduq tumani", neighborhoods: getNeighborhoods() },
      xojaobod_t: { name: "Xo'jaobod tumani", neighborhoods: getNeighborhoods() },
      qorgontepa_t: { name: "Qorg'ontepa tumani", neighborhoods: getNeighborhoods() },
      marhamat_t: { name: "Marhamat tumani", neighborhoods: getNeighborhoods() },
      oltinkol_t: { name: "Oltinko'l tumani", neighborhoods: getNeighborhoods() },
      paxtaobod_t: { name: "Paxtaobod tumani", neighborhoods: getNeighborhoods() },
      shahrixon_t: { name: "Shahrixon tumani", neighborhoods: getNeighborhoods() },
      ulugnor_t: { name: "Ulug'nor tumani", neighborhoods: getNeighborhoods() }
    }
  },
  namangan: {
    name: "Namangan",
    districts: {
      namangan_sh: { name: "Namangan shahri", neighborhoods: getNeighborhoods() },
      chortoq_t: { name: "Chortoq tumani", neighborhoods: getNeighborhoods() },
      chust_t: { name: "Chust tumani", neighborhoods: getNeighborhoods() },
      kosonsoy_t: { name: "Kosonsoy tumani", neighborhoods: getNeighborhoods() },
      mingbuloq_t: { name: "Mingbuloq tumani", neighborhoods: getNeighborhoods() },
      namangan_t: { name: "Namangan tumani", neighborhoods: getNeighborhoods() },
      norin_t: { name: "Norin tumani", neighborhoods: getNeighborhoods() },
      pop_t: { name: "Pop tumani", neighborhoods: getNeighborhoods() },
      toraqorgon_t: { name: "To'raqo'rg'on tumani", neighborhoods: getNeighborhoods() },
      uchqorgon_t: { name: "Uchqo'rg'on tumani", neighborhoods: getNeighborhoods() },
      uychi_t: { name: "Uychi tumani", neighborhoods: getNeighborhoods() },
      yangiqorgon_t: { name: "Yangiqo'rg'on tumani", neighborhoods: getNeighborhoods() }
    }
  },
  buxoro: {
    name: "Buxoro",
    districts: {
      buxoro_sh: { name: "Buxoro shahri", neighborhoods: getNeighborhoods() },
      kogon_sh: { name: "Kogon shahri", neighborhoods: getNeighborhoods() },
      olot_t: { name: "Olot tumani", neighborhoods: getNeighborhoods() },
      buxoro_t: { name: "Buxoro tumani", neighborhoods: getNeighborhoods() },
      gijduvon_t: { name: "G'ijduvon tumani", neighborhoods: getNeighborhoods() },
      jondor_t: { name: "Jondor tumani", neighborhoods: getNeighborhoods() },
      kogon_t: { name: "Kogon tumani", neighborhoods: getNeighborhoods() },
      qorakol_t: { name: "Qorako'l tumani", neighborhoods: getNeighborhoods() },
      qorovulbozor_t: { name: "Qorovulbozor tumani", neighborhoods: getNeighborhoods() },
      peshku_t: { name: "Peshku tumani", neighborhoods: getNeighborhoods() },
      romitan_t: { name: "Romitan tumani", neighborhoods: getNeighborhoods() },
      shofirkon_t: { name: "Shofirkon tumani", neighborhoods: getNeighborhoods() },
      vobkent_t: { name: "Vobkent tumani", neighborhoods: getNeighborhoods() }
    }
  },
  qashqadaryo: {
    name: "Qashqadaryo",
    districts: {
      qarshi_sh: { name: "Qarshi shahri", neighborhoods: getNeighborhoods() },
      shahrisabz_sh: { name: "Shahrisabz shahri", neighborhoods: getNeighborhoods() },
      chiroqchi_t: { name: "Chiroqchi tumani", neighborhoods: getNeighborhoods() },
      dehqonobod_t: { name: "Dehqonobod tumani", neighborhoods: getNeighborhoods() },
      guzor_t: { name: "G'uzor tumani", neighborhoods: getNeighborhoods() },
      qamashi_t: { name: "Qamashi tumani", neighborhoods: getNeighborhoods() },
      qarshi_t: { name: "Qarshi tumani", neighborhoods: getNeighborhoods() },
      koson_t: { name: "Koson tumani", neighborhoods: getNeighborhoods() },
      kasbi_t: { name: "Kasbi tumani", neighborhoods: getNeighborhoods() },
      kitob_t: { name: "Kitob tumani", neighborhoods: getNeighborhoods() },
      mirishkor_t: { name: "Mirishkor tumani", neighborhoods: getNeighborhoods() },
      muborak_t: { name: "Muborak tumani", neighborhoods: getNeighborhoods() },
      nishon_t: { name: "Nishon tumani", neighborhoods: getNeighborhoods() },
      shahrisabz_t: { name: "Shahrisabz tumani", neighborhoods: getNeighborhoods() },
      yakkabog_t: { name: "Yakkabog' tumani", neighborhoods: getNeighborhoods() },
      kokdala_t: { name: "Ko'kdala tumani", neighborhoods: getNeighborhoods() }
    }
  },
  surxondaryo: {
    name: "Surxondaryo",
    districts: {
      termiz_sh: { name: "Termiz shahri", neighborhoods: getNeighborhoods() },
      angor_t: { name: "Angor tumani", neighborhoods: getNeighborhoods() },
      bandixon_t: { name: "Bandixon tumani", neighborhoods: getNeighborhoods() },
      boysun_t: { name: "Boysun tumani", neighborhoods: getNeighborhoods() },
      denov_t: { name: "Denov tumani", neighborhoods: getNeighborhoods() },
      jarqorgon_t: { name: "Jarqo'rg'on tumani", neighborhoods: getNeighborhoods() },
      qiziriq_t: { name: "Qiziriq tumani", neighborhoods: getNeighborhoods() },
      qumqorgon_t: { name: "Qumqo'rg'on tumani", neighborhoods: getNeighborhoods() },
      muzrabot_t: { name: "Muzrabot tumani", neighborhoods: getNeighborhoods() },
      oltinsoy_t: { name: "Oltinsoy tumani", neighborhoods: getNeighborhoods() },
      sariosiyo_t: { name: "Sariosiyo tumani", neighborhoods: getNeighborhoods() },
      sherobod_t: { name: "Sherobod tumani", neighborhoods: getNeighborhoods() },
      shorchi_t: { name: "Sho'rchi tumani", neighborhoods: getNeighborhoods() },
      termiz_t: { name: "Termiz tumani", neighborhoods: getNeighborhoods() },
      uzun_t: { name: "Uzun tumani", neighborhoods: getNeighborhoods() }
    }
  },
  jizzax: {
    name: "Jizzax",
    districts: {
      jizzax_sh: { name: "Jizzax shahri", neighborhoods: getNeighborhoods() },
      arnasoy_t: { name: "Arnasoy tumani", neighborhoods: getNeighborhoods() },
      baxmal_t: { name: "Baxmal tumani", neighborhoods: getNeighborhoods() },
      dostlik_t: { name: "Do'stlik tumani", neighborhoods: getNeighborhoods() },
      forish_t: { name: "Forish tumani", neighborhoods: getNeighborhoods() },
      gallaorol_t: { name: "G'allaorol tumani", neighborhoods: getNeighborhoods() },
      sharof_rashidov_t: { name: "Sharof Rashidov tumani", neighborhoods: getNeighborhoods() },
      mirzachol_t: { name: "Mirzacho'l tumani", neighborhoods: getNeighborhoods() },
      paxtakor_t: { name: "Paxtakor tumani", neighborhoods: getNeighborhoods() },
      yangiobod_t: { name: "Yangiobod tumani", neighborhoods: getNeighborhoods() },
      zomin_t: { name: "Zomin tumani", neighborhoods: getNeighborhoods() },
      zafarobod_t: { name: "Zafarobod tumani", neighborhoods: getNeighborhoods() },
      zarbdor_t: { name: "Zarbdor tumani", neighborhoods: getNeighborhoods() }
    }
  },
  sirdaryo: {
    name: "Sirdaryo",
    districts: {
      guliston_sh: { name: "Guliston shahri", neighborhoods: getNeighborhoods() },
      shirin_sh: { name: "Shirin shahri", neighborhoods: getNeighborhoods() },
      yangiyer_sh: { name: "Yangiyer shahri", neighborhoods: getNeighborhoods() },
      oqoltin_t: { name: "Oqoltin tumani", neighborhoods: getNeighborhoods() },
      boyovut_t: { name: "Boyovut tumani", neighborhoods: getNeighborhoods() },
      guliston_t: { name: "Guliston tumani", neighborhoods: getNeighborhoods() },
      xovos_t: { name: "Xovos tumani", neighborhoods: getNeighborhoods() },
      mirzaobod_t: { name: "Mirzaobod tumani", neighborhoods: getNeighborhoods() },
      sardoba_t: { name: "Sardoba tumani", neighborhoods: getNeighborhoods() },
      sayxunobod_t: { name: "Sayxunobod tumani", neighborhoods: getNeighborhoods() },
      sirdaryo_t: { name: "Sirdaryo tumani", neighborhoods: getNeighborhoods() }
    }
  },
  navoiy: {
    name: "Navoiy",
    districts: {
      navoiy_sh: { name: "Navoiy shahri", neighborhoods: getNeighborhoods() },
      zarafshon_sh: { name: "Zarafshon shahri", neighborhoods: getNeighborhoods() },
      konimex_t: { name: "Konimex tumani", neighborhoods: getNeighborhoods() },
      qiziltepa_t: { name: "Qiziltepa tumani", neighborhoods: getNeighborhoods() },
      xatirchi_t: { name: "Xatirchi tumani", neighborhoods: getNeighborhoods() },
      navbahor_t: { name: "Navbahor tumani", neighborhoods: getNeighborhoods() },
      karmana_t: { name: "Karmana tumani", neighborhoods: getNeighborhoods() },
      nurota_t: { name: "Nurota tumani", neighborhoods: getNeighborhoods() },
      tomdi_t: { name: "Tomdi tumani", neighborhoods: getNeighborhoods() },
      uchquduq_t: { name: "Uchquduq tumani", neighborhoods: getNeighborhoods() }
    }
  },
  xorazm: {
    name: "Xorazm",
    districts: {
      urganch_sh: { name: "Urganch shahri", neighborhoods: getNeighborhoods() },
      xiva_sh: { name: "Xiva shahri", neighborhoods: getNeighborhoods() },
      bogot_t: { name: "Bog'ot tumani", neighborhoods: getNeighborhoods() },
      gurlan_t: { name: "Gurlan tumani", neighborhoods: getNeighborhoods() },
      xonqa_t: { name: "Xonqa tumani", neighborhoods: getNeighborhoods() },
      hazorasp_t: { name: "Hazorasp tumani", neighborhoods: getNeighborhoods() },
      xiva_t: { name: "Xiva tumani", neighborhoods: getNeighborhoods() },
      qoshkopir_t: { name: "Qo'shko'pir tumani", neighborhoods: getNeighborhoods() },
      shovot_t: { name: "Shovot tumani", neighborhoods: getNeighborhoods() },
      urganch_t: { name: "Urganch tumani", neighborhoods: getNeighborhoods() },
      yangiariq_t: { name: "Yangiariq tumani", neighborhoods: getNeighborhoods() },
      yangibozor_t: { name: "Yangibozor tumani", neighborhoods: getNeighborhoods() },
      tuproqqala_t: { name: "Tuproqqal'a tumani", neighborhoods: getNeighborhoods() }
    }
  },
  qoraqalpogiston: {
    name: "Qoraqalpog'iston",
    districts: {
      nukus_sh: { name: "Nukus shahri", neighborhoods: getNeighborhoods() },
      amudaryo_t: { name: "Amudaryo tumani", neighborhoods: getNeighborhoods() },
      beruniy_t: { name: "Beruniy tumani", neighborhoods: getNeighborhoods() },
      chimboy_t: { name: "Chimboy tumani", neighborhoods: getNeighborhoods() },
      ellikqala_t: { name: "Ellikqala tumani", neighborhoods: getNeighborhoods() },
      kegeyli_t: { name: "Kegeyli tumani", neighborhoods: getNeighborhoods() },
      moynoq_t: { name: "Mo'ynoq tumani", neighborhoods: getNeighborhoods() },
      nukus_t: { name: "Nukus tumani", neighborhoods: getNeighborhoods() },
      qanlikol_t: { name: "Qanliko'l tumani", neighborhoods: getNeighborhoods() },
      qongirot_t: { name: "Qo'ng'irot tumani", neighborhoods: getNeighborhoods() },
      qoraozak_t: { name: "Qorao'zak tumani", neighborhoods: getNeighborhoods() },
      shumanay_t: { name: "Shumanay tumani", neighborhoods: getNeighborhoods() },
      taxtakopir_t: { name: "Taxtako'pir tumani", neighborhoods: getNeighborhoods() },
      tortkol_t: { name: "To'rtko'l tumani", neighborhoods: getNeighborhoods() },
      xojayli_t: { name: "Xo'jayli tumani", neighborhoods: getNeighborhoods() },
      taxiatosh_t: { name: "Taxiatosh tumani", neighborhoods: getNeighborhoods() },
      bozatov_t: { name: "Bo'zatov tumani", neighborhoods: getNeighborhoods() }
    }
  }
};
