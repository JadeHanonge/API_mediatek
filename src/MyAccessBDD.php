<?php
include_once("AccessBDD.php");

/**
 * Classe de construction des requêtes SQL
 * hérite de AccessBDD qui contient les requêtes de base
 * Pour ajouter une requête :
 * - créer la fonction qui crée une requête (prendre modèle sur les fonctions 
 *   existantes qui ne commencent pas par 'traitement')
 * - ajouter un 'case' dans un des switch des fonctions redéfinies 
 * - appeler la nouvelle fonction dans ce 'case'
 */
class MyAccessBDD extends AccessBDD {
	    
    /**
     * constructeur qui appelle celui de la classe mère
     */
    public function __construct(){
        try{
            parent::__construct();
        }catch(\Exception $e){
            throw $e;
        }
    }

    /**
     * demande de recherche
     * @param string $table
     * @param array|null $champs nom et valeur de chaque champ
     * @return array|null tuples du résultat de la requête ou null si erreur
     * @override
     */	
    protected function traitementSelect(string $table, ?array $champs) : ?array{
        switch($table){  
            case "livre" :
                return $this->selectAllLivres();
            case "dvd" :
                return $this->selectAllDvd();
            case "revue" :
                return $this->selectAllRevues();
            case "exemplaire" :
                return $this->selectExemplairesRevue($champs);
            case "genre" :
            case "public" :
            case "rayon" :
            case "etape" :
            case "etat" :
                // select portant sur une table contenant juste id et libelle
                return $this->selectTableSimple($table);
            case "commande" :
                return $this->selectAllCommandes();
            case "utilisateur" :
                return $this->selectAllUtilisateur();
            case "abonnement" :
                return $this->selectAllAbonnement();
            case "" :
                // return $this->uneFonction(parametres);
            default:
                // cas général
                return $this->selectTuplesOneTable($table, $champs);
        }	
    }

    /**
     * demande d'ajout (insert)
     * @param string $table
     * @param array|null $champs nom et valeur de chaque champ
     * @return int|null nombre de tuples ajoutés ou null si erreur
     * @override
     */	
    protected function traitementInsert(string $table, ?array $champs) : ?int{
        switch($table){
            case "" :
                // return $this->uneFonction(parametres);
            case "commande" :
                return $this->insertCommandeLivre($champs);
            case "exemplaire" :
                return $this->insertTupleExemplaire($champs);
            case "abonnement" :
                return $this->insertAbonnement($champs);
            default:                    
                // cas général
                return $this->insertOneTupleOneTable($table, $champs);	
        }
    }
    
    /**
     * demande de modification (update)
     * @param string $table
     * @param string|null $id
     * @param array|null $champs nom et valeur de chaque champ
     * @return int|null nombre de tuples modifiés ou null si erreur
     * @override
     */	
    protected function traitementUpdate(string $table, ?string $id, ?array $champs) : ?int{
        switch($table){
            case "" :
                // return $this->uneFonction(parametres);
            case "commande" :
                return $this->updateCommandeLivre($id, $champs);
            default:                    
                // cas général
                return $this->updateOneTupleOneTable($table, $id, $champs);
        }	
    }  
    
    /**
     * demande de suppression (delete)
     * @param string $table
     * @param array|null $champs nom et valeur de chaque champ
     * @return int|null nombre de tuples supprimés ou null si erreur
     * @override
     */	
    protected function traitementDelete(string $table, ?array $champs) : ?int{
        switch($table){
            case "" :
                // return $this->uneFonction(parametres);
            case "commande" :
                return $this->deleteCommandeLivre($champs);
            case "abonnement" :
                return $this->deleteAbonnement($champs);
            default:                    
                // cas général
                return $this->deleteTuplesOneTable($table, $champs);	
        }
    }	    
        
    /**
     * récupère les tuples d'une seule table
     * @param string $table
     * @param array|null $champs
     * @return array|null 
     */
    private function selectTuplesOneTable(string $table, ?array $champs) : ?array{
        if(empty($champs)){
            // tous les tuples d'une table
            $requete = "select * from $table;";
            return $this->conn->queryBDD($requete);  
        }else{
            // tuples spécifiques d'une table
            $requete = "select * from $table where ";
            foreach ($champs as $key => $value){
                $requete .= "$key=:$key and ";
            }
            // (enlève le dernier and)
            $requete = substr($requete, 0, strlen($requete)-5);	          
            return $this->conn->queryBDD($requete, $champs);
        }
    }	

    /**
     * demande d'ajout (insert) d'un tuple dans une table
     * @param string $table
     * @param array|null $champs
     * @return int|null nombre de tuples ajoutés (0 ou 1) ou null si erreur
     */	
    private function insertOneTupleOneTable(string $table, ?array $champs) : ?int{
        if(empty($champs)){
            return null;
        }
        // construction de la requête
        $requete = "insert into $table (";
        foreach ($champs as $key => $value){
            $requete .= "$key,";
        }
        // (enlève la dernière virgule)
        $requete = substr($requete, 0, strlen($requete)-1);
        $requete .= ") values (";
        foreach ($champs as $key => $value){
            $requete .= ":$key,";
        }
        // (enlève la dernière virgule)
        $requete = substr($requete, 0, strlen($requete)-1);
        $requete .= ");";
        return $this->conn->updateBDD($requete, $champs);
    }

    /**
     * methode qui récupère le dernier numéro d'un exemplaire donner
     * @param string $id
     * @return int|null le numéro du dernier examplaire ou null si erreur
     */
    private function getDernierNumExemplaire(string $id) : int {
        $requete = "SELECT MAX(numero) as maxNum FROM exemplaire WHERE id = :id";
        $params = ["id" => $id];
        $result = $this->conn->queryBDD($requete, $params);
        return $result[0]["maxNum"] ?? 0;
    }

    /**
     * demande d'ajout(insert) d'un exemplaire
     * @param array $champs
     * @return bool return true si l'insert a fonctionner et false si erreur
     */
    private function insertTupleExemplaire(array $champs) : bool {
        try{

            $nb = $champs["Numero"];

            $id = $champs["Id"];
            $numero = $this->getDernierNumExemplaire($id) + 1;

            error_log("max num");
            error_log(print_r($numero, true));

            for($i = 1; $i <= $nb; $i++){
                $this->insertOneTupleOneTable("exemplaire", [
                    "id" => $champs["Id"],
                    "numero" => $numero,
                    "dateAchat" => $champs["DateAchat"],
                    "photo" => "",
                    "idEtat" => "00001"
                ]);
                $numero ++;

                error_log(print_r($numero, true));
                error_log(print_r($nb, true));


            }

            return true;
        }catch (Exception $e) {
            return false;
        }
    }

    /**
     * demande d'ajout(insert) d'une commande de livre
     * @param array $champs
     * @return bool return true si l'insert a fonctionner et false si erreur
     */
    private function insertCommandeLivre(array $champs) : bool {
        try{


            $this->insertOneTupleOneTable("commande", [
                "id" => $champs["Id"],
                "dateCommande"=> $champs["DateCommande"],
                "montant" => $champs["Montant"]
            ]);

            $this->insertOneTupleOneTable("commandedocument", [
                "id" => $champs["Id"],
                "nbExemplaire" => $champs["NbExemplaire"],
                "idLivreDvd" => $champs["IdLivreDvd"]
            ]);

            $this->insertOneTupleOneTable("suivi", [
                "id" => $champs["Id"],
                "idCommande" => $champs["Id"],
                "idEtape" => $champs["IdEtape"]
            ]);

            return true;


        }catch (Exception $e) {
            return false;
        }
    }

    /**
     * demande d'ajout(insert) d'abonnement
     * @param array $champs
     * @return bool return true si l'insert a fonctionner et false si erreur
     */
    private function insertAbonnement(array $champs) : bool {
        try{

            $this->insertOneTupleOneTable("commande", [
                "id" => $champs["Id"],
                "dateCommande" => $champs["DateCommande"],
                "montant" => $champs["Montant"]
            ]);

            $this->insertOneTupleOneTable("abonnement", [
                "id" => $champs["Id"],
                "dateFinAbonnement" => $champs["DateFinAbonnement"],
                "idRevue" => $champs["IdRevue"]
            ]);
            return true;

        }catch (Exception $e) {
            return false;
        }
    }

    /**
     * demande de modification (update) d'un tuple dans une table
     * @param string $table
     * @param string\null $id
     * @param array|null $champs
     * @return int|null nombre de tuples modifiés (0 ou 1) ou null si erreur
     */
    private function updateOneTupleOneTable(string $table, ?string $id, ?array $champs) : ?int {
        if(empty($champs)){
            return null;
        }
        if(is_null($id)){
            return null;
        }
        // construction de la requête
        $requete = "update $table set ";
        foreach ($champs as $key => $value){
            $requete .= "$key=:$key,";
        }
        // (enlève la dernière virgule)
        $requete = substr($requete, 0, strlen($requete)-1);
        $champs["id"] = $id;
        $requete .= " where id=:id;";
        return $this->conn->updateBDD($requete, $champs);
    }

    /**
     * demande de modification (update) d'une commande
     * @param array $champs
     * @return bool return true si l'insert a fonctionner et false si erreur
     */
    private function updateCommandeLivre(?string $id, array $champs) : bool{
        try{
            $this->updateOneTupleOneTable("suivi", $id, [
                "id" => $champs["Id"],
                "idCommande" => $champs["Id"],
                "IdEtape" => $champs["IdEtape"]
            ]);

            $this->updateOneTupleOneTable("commandedocument", $id, [
                "id" => $champs["Id"],
                "nbExemplaire" => $champs["NbExemplaire"],
                "idLivreDvd" => $champs["IdLivreDvd"]
            ]);

            $this->updateOneTupleOneTable("commande", $id, [
                "id" => $champs["Id"],
                "dateCommande" => $champs["DateCommande"],
                "montant" => $champs["Montant"]
            ]);
            return true;

        }catch (Exception $e){
            return false;
        }
    }


    /**
     * demande de suppression (delete) d'un ou plusieurs tuples dans une table
     * @param string $table
     * @param array|null $champs
     * @return int|null nombre de tuples supprimés ou null si erreur
     */
    private function deleteTuplesOneTable(string $table, ?array $champs) : ?int{
        if(empty($champs)){
            return null;
        }
        // construction de la requête
        $requete = "delete from $table where ";
        foreach ($champs as $key => $value){
            $requete .= "$key=:$key and ";
        }
        // (enlève le dernier and)
        $requete = substr($requete, 0, strlen($requete)-5);
        return $this->conn->updateBDD($requete, $champs);
    }

    /**
     * demande de suppression (delete) d'une commande
     * @param array $champs
     * @return bool return true si l'insert a fonctionner et false si erreur
     */
    private function deleteCommandeLivre(array $champs) : bool{
        try{

            $this->deleteTuplesOneTable("suivi", [
                "id" => $champs["Id"],
                "idCommande" => $champs["Id"],
                "IdEtape" => $champs["IdEtape"]
            ]);

            $this->deleteTuplesOneTable("commandedocument", [
                "id" => $champs["Id"],
                "nbExemplaire" => $champs["NbExemplaire"],
                "idLivreDvd" => $champs["IdLivreDvd"]
            ]);

            $this->deleteTuplesOneTable("commande", [
                "id" => $champs["Id"],
                "dateCommande" => $champs["DateCommande"],
                "montant" => $champs["Montant"]
            ]);
            return true;

        }catch (Exception $e){
            return false;
        }

    }

    /**
     * demande de suppression (delete) d'un abonnement
     * @param array $champs
     * @return bool return true si l'insert a fonctionner et false si erreur
     */
    private function deleteAbonnement(array $champs) : bool{
        try{

            $this->deleteTuplesOneTable("commande", [
                "id" => $champs["Id"],
                "dateCommande" => $champs["DateCommande"],
                "montant" => $champs["Montant"]
            ]);

            $this->deleteTuplesOneTable("abonnement", [
                "id" => $champs["Id"],
                "dateFinAbonnement" => $champs["DateFinAbonnement"],
                "idRevue" => $champs["IdRevue"]
            ]);
            return true;
        }catch (Exception $e){
            return false;
        }
    }

    /**
     * récupère toutes les lignes d'une table simple (qui contient juste id et libelle)
     * @param string $table
     * @return array|null
     */
    private function selectTableSimple(string $table) : ?array{
        $requete = "select * from $table order by libelle;";
        return $this->conn->queryBDD($requete);
    }

    /**
     * récupère toutes les lignes de la table Livre et les tables associées
     * @return array|null
     */
    private function selectAllLivres() : ?array{
        $requete = "Select l.id, l.ISBN, l.auteur, d.titre, d.image, l.collection, ";
        $requete .= "d.idrayon, d.idpublic, d.idgenre, g.libelle as genre, p.libelle as lePublic, r.libelle as rayon ";
        $requete .= "from livre l join document d on l.id=d.id ";
        $requete .= "join genre g on g.id=d.idGenre ";
        $requete .= "join public p on p.id=d.idPublic ";
        $requete .= "join rayon r on r.id=d.idRayon ";
        $requete .= "order by titre ";
        return $this->conn->queryBDD($requete);
    }

    /**
     * récupère toutes les lignes de la table Commande et les tables associées
     * @return array|null
     */
    private function selectAllCommandes() : ?array{
        $requete = "Select c.id, c.dateCommande, c.montant, cd.nbExemplaire, s.IdEtape, ";
        $requete .= "s.idCommande, cd.idLivreDvd, e.libelle as etape ";
        $requete .= "from commande c join commandedocument cd on c.id=cd.id ";
        $requete .= "join suivi s on s.idCommande=c.id ";
        $requete .= "join etape e on e.id=s.idEtape ";
        $requete .= "order by c.dateCommande ";
        return $this->conn->queryBDD($requete);
    }

    /**
     * récupère toutes les lignes de la table Abonnement et les table associées
     * @return array|null
     */
    private function selectAllAbonnement() : ?array{
        $requete = "Select c.id, c.dateCommande, c.montant, ";
        $requete .= "a.dateFinAbonnement, a.idRevue ";
        $requete .= "from commande c join abonnement a on c.id=a.id ";
        $requete .= "order by c.dateCommande ";
        return $this->conn->queryBDD($requete);
    }

    /**
     * récupère toutes les lignes de la table Utilisateur
     * @return array|null
     */
    private function selectAllUtilisateur() : ?array{
        $requete = "Select u.User, u.Pwd, u.IdService, ";
        $requete .= "s.Libelle as service ";
        $requete .= "from utilisateur u join service s on u.IdService=s.Id ";
        return $this->conn->queryBDD($requete);
    }

    /**
     * récupère toutes les lignes de la table DVD et les tables associées
     * @return array|null
     */
    private function selectAllDvd() : ?array{
        $requete = "Select l.id, l.duree, l.realisateur, d.titre, d.image, l.synopsis, ";
        $requete .= "d.idrayon, d.idpublic, d.idgenre, g.libelle as genre, p.libelle as lePublic, r.libelle as rayon ";
        $requete .= "from dvd l join document d on l.id=d.id ";
        $requete .= "join genre g on g.id=d.idGenre ";
        $requete .= "join public p on p.id=d.idPublic ";
        $requete .= "join rayon r on r.id=d.idRayon ";
        $requete .= "order by titre ";
        return $this->conn->queryBDD($requete);
    }

    /**
     * récupère toutes les lignes de la table Revue et les tables associées
     * @return array|null
     */
    private function selectAllRevues() : ?array{
        $requete = "Select l.id, l.periodicite, d.titre, d.image, l.delaiMiseADispo, ";
        $requete .= "d.idrayon, d.idpublic, d.idgenre, g.libelle as genre, p.libelle as lePublic, r.libelle as rayon ";
        $requete .= "from revue l join document d on l.id=d.id ";
        $requete .= "join genre g on g.id=d.idGenre ";
        $requete .= "join public p on p.id=d.idPublic ";
        $requete .= "join rayon r on r.id=d.idRayon ";
        $requete .= "order by titre ";
        return $this->conn->queryBDD($requete);
    }

    /**
     * récupère tous les exemplaires d'une revue
     * @param array|null $champs
     * @return array|null
     */
    private function selectExemplairesRevue(?array $champs) : ?array{
        if(empty($champs)){
            return null;
        }
        if(!array_key_exists('id', $champs)){
            return null;
        }
        $champNecessaire['id'] = $champs['id'];
        $requete = "Select e.id, e.numero, e.dateAchat, e.photo, e.idEtat ";
        $requete .= "from exemplaire e join document d on e.id=d.id ";
        $requete .= "where e.id = :id ";
        $requete .= "order by e.dateAchat DESC";
        return $this->conn->queryBDD($requete, $champNecessaire);
    }

}
