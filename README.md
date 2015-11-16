#Login

```
Method: POST
Path: /endpoint.php/login/$username/$password

Example:
POST /endpoint.php/login/myusername/mypassword
```

```
{
  "status": "success",
  "token": "af651af328435995e85d722cbf9bc5b4"
}
```

#Search

```
Method: GET
Path: /endpoint.php/search/$search/$token

Example:
GET /endpoint.php/search/the blacklist 1080p/af651af328435995e85d722cbf9bc5b4
```

```
{
  "status": "success",
  "result": [
    {
      "category": 44,
      "categoryName": "P2P/TV/HD",
      "sccId": 1217277,
      "name": "The.Blacklist.S03E04.1080p.WEB-DL.DD5.1.H.264-Oosh",
      "size": 1669.12,
      "files": 1,
      "added": "2015-10-24 17:33:03"
    },
    {
      "category": 44,
      "categoryName": "P2P/TV/HD",
      "sccId": 1209441,
      "name": "The.Blacklist.S03E02.1080p.WEB-DL.DD5.1.H.264-Oosh",
      "size": 1679.36,
      "files": 1,
      "added": "2015-10-14 00:19:57"
    },
    ...
}
```

#Download

```
Method: GET
Path: /endpoint.php/download/$sccId/$token

Example:
GET /endpoint.php/download/1217277/af651af328435995e85d722cbf9bc5b4
```

```
The actual torrent
```

#Multidownload

```
Method: POST
Path: /endpoint.php/multidownload/$token
Body: json_encode(array($id, $id2, $id3, etc..))

Example:
POST /endpoint.php/multidownload/af651af328435995e85d722cbf9bc5b4
[825635, 828222, 958780]
```

```
Gives .zip with wanted torrents
```