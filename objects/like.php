<?php
global $global, $config;
if (!isset($global['systemRootPath'])) {
    require_once '../videos/configuration.php';
}

require_once $global['systemRootPath'] . 'objects/user.php';

class Like
{
    private $id;
    private $like;
    private $videos_id;
    private $users_id;

    public function __construct($like, $videos_id)
    {
        if (!User::isLogged()) {
            header('Content-Type: application/json');
            die('{"error":"'.__("Permission denied").'"}');
        }
        $this->videos_id = $videos_id;
        $this->users_id = User::getId();
        $this->load();
        // if click again in the same vote, remove the vote
        if ($this->like == $like) {
            $like = 0;
            if ($this->like==1) {
                Video::updateLikesDislikes($videos_id, 'likes', '-1');
            } elseif ($this->like==-1) {
                Video::updateLikesDislikes($videos_id, 'dislikes', '-1');
            }
        } else {
            if (!empty($this->like)) {
                // need to remove some like or dislike
                if ($like==1) {
                    Video::updateLikesDislikes($videos_id, 'dislikes', '-1');
                } elseif ($like==-1) {
                    Video::updateLikesDislikes($videos_id, 'likes', '-1');
                }
            }
            if ($like==1) {
                Video::updateLikesDislikes($videos_id, 'likes', '+1');
            } elseif ($like==-1) {
                Video::updateLikesDislikes($videos_id, 'dislikes', '+1');
            }
        }
        //exit;
        $this->setLike($like);
        $saved = $this->save();
    }

    private function setLike($like)
    {
        $like = intval($like);
        if (!in_array($like, [0,1,-1])) {
            $like = 0;
        }
        $this->like = $like;
    }

    public function load()
    {
        $like = $this->getLike();
        if (empty($like)) {
            return false;
        }
        foreach ($like as $key => $value) {
            $this->$key = $value;
        }
    }

    private function getLike()
    {
        global $global;
        if (empty($this->users_id) || empty($this->videos_id)) {
            header('Content-Type: application/json');
            die('{"error":"You must have user and videos set to get a like"}');
        }
        $sql = "SELECT * FROM likes WHERE users_id = ? AND videos_id = ".$this->videos_id." LIMIT 1;";
        $res = sqlDAL::readSql($sql, "i", [$this->users_id]);
        $dbLike = sqlDAL::fetchAssoc($res);
        sqlDAL::close($res);
        return $dbLike;
    }

    private function save()
    {
        global $global;
        if (!User::isLogged()) {
            header('Content-Type: application/json');
            die('{"error":"'.__("Permission denied").'"}');
        }
        if (!empty($this->id)) {
            $sql = "UPDATE likes SET `like` = ?, modified = now() WHERE id = ?;";
            $res = sqlDAL::writeSql($sql, "ii", [$this->like, $this->id]);
        } else {
            $sql = "INSERT INTO likes (`like`,users_id, videos_id, created, modified) VALUES (?, ?, ?, now(), now());";
            $res = sqlDAL::writeSql($sql, "iii", [$this->like, $this->users_id, $this->videos_id]);
        }
        //echo $sql;
        if ($global['mysqli']->errno!=0) {
            die('Error : (' . $global['mysqli']->errno . ') ' . $global['mysqli']->error);
        }
        return $res;
    }

    public static function getLikes($videos_id)
    {
        global $global, $_getLikes;

        if (!isset($_getLikes)) {
            $_getLikes = [];
        }

        if (!empty($_getLikes[$videos_id])) {
            return $_getLikes[$videos_id];
        }

        $obj = new stdClass();
        $obj->videos_id = $videos_id;
        $obj->likes = 0;
        $obj->dislikes = 0;
        $obj->myVote = self::getMyVote($videos_id);

        $sql = "SELECT count(*) as total FROM likes WHERE videos_id = ? AND `like` = 1 "; // like
        $res = sqlDAL::readSql($sql, "i", [$videos_id]);
        $row = sqlDAL::fetchAssoc($res);
        sqlDAL::close($res);
        if ($global['mysqli']->errno!=0) {
            die($sql . '\nError : (' . $global['mysqli']->errno . ') ' . $global['mysqli']->error);
        }
        $obj->likes = intval($row['total']);

        $sql = "SELECT count(*) as total FROM likes WHERE videos_id = ? AND `like` = -1 "; // dislike

        $res = sqlDAL::readSql($sql, "i", [$videos_id]);
        $row = sqlDAL::fetchAssoc($res);
        sqlDAL::close($res);
        if ($global['mysqli']->errno!=0) {
            die($sql.'\nError : (' . $global['mysqli']->errno . ') ' . $global['mysqli']->error);
        }
        $obj->dislikes = intval($row['total']);
        $_getLikes[$videos_id] = $obj;
        return $obj;
    }

    public static function getTotalLikes()
    {
        global $global;

        $obj = new stdClass();
        $obj->likes = 0;
        $obj->dislikes = 0;

        $sql = "SELECT count(*) as total FROM likes WHERE `like` = 1 "; // like
        $res = sqlDAL::readSql($sql);
        $row = sqlDAL::fetchAssoc($res);
        sqlDAL::close($res);
        if (!$res) {
            die($sql . '\nError : (' . $global['mysqli']->errno . ') ' . $global['mysqli']->error);
        }
        $obj->likes = intval($row['total']);

        $sql = "SELECT count(*) as total FROM likes WHERE `like` = -1 "; // dislike
        $res = sqlDAL::readSql($sql);
        $row = sqlDAL::fetchAssoc($res);
        sqlDAL::close($res);
        if (!$res) {
            die($sql.'\nError : (' . $global['mysqli']->errno . ') ' . $global['mysqli']->error);
        }
        $obj->dislikes = intval($row['total']);
        return $obj;
    }

    public static function getMyVote($videos_id)
    {
        global $global;
        if (!User::isLogged()) {
            return 0;
        }
        $id = User::getId();
        $sql = "SELECT `like` FROM likes WHERE videos_id = ? AND users_id = ? "; // like

        $res = sqlDAL::readSql($sql, "ii", [$videos_id,$id]);
        $dbLike = sqlDAL::fetchAssoc($res);
        sqlDAL::close($res);
        if ($dbLike!=false) {
            return intval($dbLike['like']);
        }
        return 0;
    }
}
