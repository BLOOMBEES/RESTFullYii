<?php

    /**
     * Base Class For Rest Actions
     *
     * Provides helper methods for rest actions
     *
     * @category   PHP
     * @package    Starship
     * @subpackage Restfullyii/actions
     * @copyright  Copyright (c) 2013 Evan Frohlich (https://github.com/evan108108)
     * @license    https://github.com/evan108108   OSS
     * @version    Release: 1.2.0
     */
    class ERestBaseAction extends CAction
    {
        /**
         * getRequestActionType
         *
         * Helps determine the action type
         *
         * @param (Mixed/Int) (id) unique identifier of the resource
         * @param (Mixed) (param1) first param sent in the request; Often subresource name
         * @param (Mixed) (param2) Second param sent in the request: Often subresource ID
         *
         * @return (String) the action type
         */
        public function getRequestActionType($id = NULL, $param1 = NULL, $param2 = NULL, $verb = 'get')
        {
            $id_is_null = is_null($id);
            $id_is_pk = $this->controller->emitRest(ERestEvent::REQ_PARAM_IS_PK, $id);
            $is_subresource = $id_is_pk && $this->controller->getSubresourceHelper()->isSubresource($this->controller->emitRest(ERestEvent::MODEL_INSTANCE), $param1, $verb);
            $is_custom_route = $this->controller->eventExists("req.$verb.$id.render");

            if ($id_is_null) {
                return 'RESOURCES';
            } else if ($is_custom_route) {
                return 'CUSTOM';
            } else if ($is_subresource && is_null($param2)) {
                return 'SUBRESOURCES';
            } else if ($is_subresource && !is_null($param2)) {
                return 'SUBRESOURCE';
            } else if ($id_is_pk && is_null($param1)) {
                return 'RESOURCE';
            } else {
                return FALSE;
            }
        }

        /**
         * finalRender
         *
         * Wrapper for ERestBehavior finalRender
         * Provides an some boilerplate for all RESTFull Render events
         *
         * @param (Callable) ($func) Should return a JSON string or Array
         */
        public function finalRender(Callable $func)
        {
            $this->controller->finalRender(
                call_user_func_array($func, [$this->controller->emitRest(ERestEvent::MODEL_VISIBLE_PROPERTIES), $this->controller->emitRest(ERestEvent::MODEL_HIDDEN_PROPERTIES)])
            );
        }

        /**
         * getModel
         *
         * Helper to retrieve the model of the current resource
         *
         * @param (Mixed/Int) (id) unique identifier of the resource
         * @param (Bool) (empty) if true will return only an empty model;
         *
         * @return (Object) (Model) the model representing the current resource
         */
        public function getModel($id = NULL, $empty = FALSE)
        {
            if ($empty) {
                return $this->controller->emitRest(ERestEvent::MODEL_INSTANCE);
            }

            return $this->controller->getResourceHelper()->prepareRestModel($id);
        }

        /**
         * getModelCount
         *
         * Helper that returns the count of models representing the requested resource
         *
         * @param (Mixed/Int) (id) unique identifier of the resource
         *
         * @return (Int) Count of found models
         */
        public function getModelCount($id = NULL)
        {
            return $this->controller->getResourceHelper()->prepareRestModel($id, TRUE);
        }

        /**
         * getModelName
         *
         * Helper that returns the name of the model associated with the requested resource
         *
         * @return (String) name of the model
         */
        public function getModelName()
        {
            return get_class($this->controller->emitRest(ERestEvent::MODEL_INSTANCE));
        }

        /**
         * getRelations
         *
         * Helper that returns the relations to include when the resource is rendered
         *
         * @return (Array) list of relations to include in output
         */
        public function getRelations()
        {
            return $this->controller->emitRest(ERestEvent::MODEL_WITH_RELATIONS,
                $this->controller->emitRest(ERestEvent::MODEL_INSTANCE)
            );
        }

        /**
         * getSubresourceCount
         *
         * Helper that will return the count of subresources of the requested resource
         *
         * @param (Mixed/Int) (id) unique identifier of the resource
         * @param (Mixed) (param1) Subresource name
         * @param (Mixed) (param2) Subresource ID
         *
         * @return (Int) Count of subresources
         */
        public function getSubresourceCount($id, $param1, $param2 = NULL)
        {
            $model = $this->getModel($id);
            return $this->querySubResource($param1, $model, true, $param2);
//            return $this->controller->emitRest(ERestEvent::MODEL_SUBRESOURCE_COUNT, [
//                    $this->getModel($id),
//                    $param1,
//                    $param2
//                ]
//            );
        }

        /**
         * getSubresourceClassName
         *
         * Helper that will return the class name that will be used to represent the requested subresource
         *
         * @param (String) Name of subresource
         *
         * @return (String) Name of subresource class
         */
        public function getSubresourceClassName($param1)
        {
            return $this->controller->getSubresourceHelper()->getSubresourceClassName(
                $this->controller->emitRest(ERestEvent::MODEL_INSTANCE),
                $param1
            );
        }

        /**
         * getSubresources
         *
         * Helper that returns a list of subresource object models
         *
         * @param (Mixed/Int) (id) the ID of the requested resource
         * @param (String) (param1) the name of the subresource
         *
         * @return (Array) Array of subresource object models
         */
        public function getSubresources($id, $param1)
        {
            /** @var CActiveRecord $model */
            $model = $this->getModel($id);
            if (NULL !== $model) {
                return $this->querySubResource($param1, $model);
            } else {
                return $this->controller->emitRest(ERestEvent::MODEL_SUBRESOURCES_FIND_ALL, [$this->getModel($id), $param1]);
            }
        }

        /**
         * getSubresources
         *
         * Helper that returns a single subresource
         *
         * @param (Mixed/Int) (id) unique identifier of the resource
         * @param (Mixed) (param1) Subresource name
         * @param (Mixed) (param2) Subresource ID
         *
         * @return (Object) the sub resource model object
         */
        public function getSubresource($id, $param1, $param2)
        {
            /** @var CActiveRecord $model */
            $model = $this->getModel($id);
            return $this->querySubResource($param1, $model, false, $param2);
//            return $this->controller->emitRest(ERestEvent::MODEL_SUBRESOURCE_FIND, [$model, $param1, $param2]);
        }

        /**
         * @param $param1
         * @param $model
         * @param $count
         * @param $pk
         *
         * @return array
         */
        private function querySubResource($param1, $model, $count = false, $pk = null)
        {
            $subresource = strtolower($param1);
            $subresources = [];
            $relations = $model->relations();
            if(count($relations)) {
                foreach ($relations as $relation => $config) {
                    $submodel = $config[1];
                    if (CActiveRecord::MANY_MANY !== $config[0] && strtolower($relation) === $subresource && class_exists($submodel)) {
                        $submodel = $submodel::model();
                        if (null != $pk) {
                            $subresources = $submodel->findByPk($pk, $submodel->getTableAlias(true) . '.' . $config[2] . ' = :pk', ['pk' => $model->$config[2]]);
                            if ($count) {
                                $subresources = (null !== $subresources) ? 1 : 0;
                            }
                        } else {
                            if($count) {
                                $subresources = $submodel->countByAttributes([$config[2] => $model->$config[2]]);
                            } else {
                                $subresources = $submodel->findAllByAttributes([$config[2] => $model->$config[2]]);
                            }
                        }
                    }
                }
            }

            return $subresources;
        }
    }
